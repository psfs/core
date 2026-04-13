<?php

namespace PSFS\services\migration;

class SqlStatementSplitter
{
    /**
     * @return array<int, string>
     */
    public function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inSingle = false;
        $inDouble = false;
        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $previous = $index > 0 ? $sql[$index - 1] : '';
            $this->updateQuoteState($char, $previous, $inSingle, $inDouble);

            if ($this->isStatementDelimiter($char, $inSingle, $inDouble)) {
                $this->appendStatementIfNotEmpty($statements, $buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $this->appendStatementIfNotEmpty($statements, $buffer);

        return $statements;
    }

    private function updateQuoteState(string $char, string $previous, bool &$inSingle, bool &$inDouble): void
    {
        if ('\\' === $previous) {
            return;
        }

        if ("'" === $char && !$inDouble) {
            $inSingle = !$inSingle;
            return;
        }

        if ('"' === $char && !$inSingle) {
            $inDouble = !$inDouble;
        }
    }

    private function isStatementDelimiter(string $char, bool $inSingle, bool $inDouble): bool
    {
        return $char === ';' && !$inSingle && !$inDouble;
    }

    /**
     * @param array<int, string> $statements
     */
    private function appendStatementIfNotEmpty(array &$statements, string $buffer): void
    {
        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }
    }
}
