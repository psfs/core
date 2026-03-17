<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$basePath = $argv[1] ?? __DIR__ . '/../src';
$basePath = realpath($basePath) ?: $basePath;
if (!is_dir($basePath)) {
    fwrite(STDERR, "Path not found: {$basePath}\n");
    exit(1);
}

$thresholds = [
    'objective' => [
        'cyclomatic' => 8,
        'method_lines' => 40,
        'class_lines' => 400,
        'public_methods' => 12,
    ],
    'max' => [
        'cyclomatic' => 10,
        'method_lines' => 60,
        'class_lines' => 600,
        'public_methods' => 18,
    ],
];

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
$files = [];
foreach ($rii as $file) {
    /** @var SplFileInfo $file */
    if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
        $files[] = $file->getPathname();
    }
}
sort($files);

    $classes = [];
    foreach ($files as $filePath) {
        $classes = array_merge($classes, analyzePhpFile($filePath));
    }

$summary = [
    'classes_total' => count($classes),
    'classes_objective_violations' => 0,
    'classes_max_violations' => 0,
    'methods_total' => 0,
    'methods_objective_violations' => 0,
    'methods_max_violations' => 0,
];

foreach ($classes as &$classMetric) {
    $summary['methods_total'] += count($classMetric['methods']);
    $classViolObjective = [];
    $classViolMax = [];

    if ($classMetric['class_lines'] > $thresholds['objective']['class_lines']) {
        $classViolObjective[] = 'class_lines';
    }
    if ($classMetric['public_methods'] > $thresholds['objective']['public_methods']) {
        $classViolObjective[] = 'public_methods';
    }
    if ($classMetric['class_lines'] > $thresholds['max']['class_lines']) {
        $classViolMax[] = 'class_lines';
    }
    if ($classMetric['public_methods'] > $thresholds['max']['public_methods']) {
        $classViolMax[] = 'public_methods';
    }

    $classMetric['objective_violations'] = $classViolObjective;
    $classMetric['max_violations'] = $classViolMax;
    if (!empty($classViolObjective)) {
        $summary['classes_objective_violations']++;
    }
    if (!empty($classViolMax)) {
        $summary['classes_max_violations']++;
    }

    foreach ($classMetric['methods'] as &$methodMetric) {
        $methodViolObjective = [];
        $methodViolMax = [];
        if ($methodMetric['cyclomatic'] > $thresholds['objective']['cyclomatic']) {
            $methodViolObjective[] = 'cyclomatic';
        }
        if ($methodMetric['method_lines'] > $thresholds['objective']['method_lines']) {
            $methodViolObjective[] = 'method_lines';
        }
        if ($methodMetric['cyclomatic'] > $thresholds['max']['cyclomatic']) {
            $methodViolMax[] = 'cyclomatic';
        }
        if ($methodMetric['method_lines'] > $thresholds['max']['method_lines']) {
            $methodViolMax[] = 'method_lines';
        }

        $methodMetric['objective_violations'] = $methodViolObjective;
        $methodMetric['max_violations'] = $methodViolMax;
        if (!empty($methodViolObjective)) {
            $summary['methods_objective_violations']++;
        }
        if (!empty($methodViolMax)) {
            $summary['methods_max_violations']++;
        }
    }
}
unset($classMetric, $methodMetric);

$result = [
    'generated_at' => date(DATE_ATOM),
    'base_path' => $basePath,
    'thresholds' => $thresholds,
    'summary' => $summary,
    'classes' => $classes,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

function analyzePhpFile(string $filePath): array
{
    $code = file_get_contents($filePath);
    if ($code === false) {
        return [];
    }
    $tokens = token_get_all($code);
    $lineMap = buildTokenLineMap($tokens);
    $classes = [];
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_CLASS) {
            continue;
        }
        if (isAnonymousClass($tokens, $i)) {
            continue;
        }

        $className = findNextTokenValue($tokens, $i + 1, T_STRING) ?? 'anonymous';
        $classStartLine = $lineMap[$i] ?? 1;
        $openBracePos = findNextBracePos($tokens, $i + 1, '{');
        if ($openBracePos === null) {
            continue;
        }

        $classData = parseClassBody($tokens, $lineMap, $openBracePos, $className);
        $classData['file'] = normalizePath($filePath);
        $classData['class_start_line'] = $classStartLine;
        $classes[] = $classData;
        $i = $classData['_end_index'];
    }

    return $classes;
}

function parseClassBody(array $tokens, array $lineMap, int $openBracePos, string $className): array
{
    $braceDepth = 1;
    $count = count($tokens);
    $publicMethods = 0;
    $methods = [];
    $classEndLine = $lineMap[$openBracePos] ?? 1;

    for ($i = $openBracePos + 1; $i < $count; $i++) {
        $token = $tokens[$i];
        if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            $braceDepth++;
        } elseif ($token === '}') {
            $braceDepth--;
            if ($braceDepth === 0) {
                $classEndLine = $lineMap[$i] ?? $classEndLine;
                break;
            }
        }

        if ($braceDepth === 1 && is_array($token) && $token[0] === T_FUNCTION) {
            $method = parseMethod($tokens, $lineMap, $i);
            if ($method !== null) {
                $methods[] = $method;
                if ($method['visibility'] === 'public') {
                    $publicMethods++;
                }
                $i = $method['_end_index'];
            }
        }
    }

    $classStartLine = $lineMap[$openBracePos] ?? 1;
    return [
        'class' => $className,
        'class_lines' => max(1, $classEndLine - $classStartLine + 1),
        'public_methods' => $publicMethods,
        'methods' => $methods,
        '_end_index' => $i ?? $openBracePos,
    ];
}

function parseMethod(array $tokens, array $lineMap, int $functionPos): ?array
{
    $methodName = null;
    $visibility = detectVisibility($tokens, $functionPos);
    $startLine = $lineMap[$functionPos] ?? 1;
    $count = count($tokens);
    $bodyStart = null;
    $endIndex = $functionPos;
    $endLine = $startLine;

    for ($i = $functionPos + 1; $i < $count; $i++) {
        $token = $tokens[$i];
        if (is_array($token) && $token[0] === T_STRING && $methodName === null) {
            $methodName = $token[1];
        }
        if ($token === '{') {
            $bodyStart = $i;
            break;
        }
        if ($token === ';') {
            $endIndex = $i;
            $endLine = $lineMap[$i] ?? $startLine;
            break;
        }
    }

    if ($methodName === null) {
        return null;
    }

    $cyclomatic = 1;
    if ($bodyStart !== null) {
        $braceDepth = 1;
        for ($j = $bodyStart + 1; $j < $count; $j++) {
            $current = $tokens[$j];
            if ($current === '{' || (is_array($current) && in_array($current[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $braceDepth++;
            } elseif ($current === '}') {
                $braceDepth--;
                if ($braceDepth === 0) {
                    $endIndex = $j;
                    $endLine = $lineMap[$j] ?? $startLine;
                    break;
                }
            }
            if (is_array($current)) {
                if (in_array($current[0], [T_IF, T_ELSEIF, T_FOR, T_FOREACH, T_WHILE, T_CASE, T_CATCH, T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR, T_COALESCE], true)) {
                    $cyclomatic++;
                }
            } elseif ($current === '?') {
                $cyclomatic++;
            }
        }
    }

    return [
        'method' => $methodName,
        'visibility' => $visibility,
        'method_lines' => max(1, $endLine - $startLine + 1),
        'cyclomatic' => $cyclomatic,
        '_end_index' => $endIndex,
    ];
}

function detectVisibility(array $tokens, int $functionPos): string
{
    for ($i = $functionPos - 1; $i >= 0; $i--) {
        $token = $tokens[$i];
        if (is_array($token)) {
            if (in_array($token[0], [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                return strtolower(str_replace('T_', '', token_name($token[0])));
            }
            if (!in_array($token[0], [T_WHITESPACE, T_STATIC, T_FINAL, T_ABSTRACT], true)) {
                break;
            }
        } elseif (in_array($token, [';', '{', '}'], true)) {
            break;
        }
    }
    return 'public';
}

function findNextTokenValue(array $tokens, int $start, int $tokenType): ?string
{
    $count = count($tokens);
    for ($i = $start; $i < $count; $i++) {
        if (is_array($tokens[$i]) && $tokens[$i][0] === $tokenType) {
            return $tokens[$i][1];
        }
    }
    return null;
}

function findNextBracePos(array $tokens, int $start, string $brace): ?int
{
    $count = count($tokens);
    for ($i = $start; $i < $count; $i++) {
        if ($tokens[$i] === $brace) {
            return $i;
        }
    }
    return null;
}

function buildTokenLineMap(array $tokens): array
{
    $line = 1;
    $map = [];
    foreach ($tokens as $index => $token) {
        $map[$index] = $line;
        $text = is_array($token) ? (string)$token[1] : (string)$token;
        $line += substr_count($text, "\n");
    }
    return $map;
}

function isAnonymousClass(array $tokens, int $classPos): bool
{
    for ($i = $classPos - 1; $i >= 0; $i--) {
        $token = $tokens[$i];
        if (is_array($token) && $token[0] === T_WHITESPACE) {
            continue;
        }
        return is_array($token) && $token[0] === T_NEW;
    }
    return false;
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}
