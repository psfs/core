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

            if ("'" === $char && '\\' !== $previous && !$inDouble) {
                $inSingle = !$inSingle;
            } elseif ('"' === $char && '\\' !== $previous && !$inSingle) {
                $inDouble = !$inDouble;
            }

            if (';' === $char && !$inSingle && !$inDouble) {
                $statement = trim($buffer);
                if ('' !== $statement) {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ('' !== $statement) {
            $statements[] = $statement;
        }

        return $statements;
    }
}
