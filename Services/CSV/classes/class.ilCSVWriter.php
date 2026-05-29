<?php

/******************************************************************************
 *
 * This file is part of ILIAS, a powerful learning management system.
 *
 * ILIAS is licensed with the GPL-3.0, you should have received a copy
 * of said license along with the source code.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 *      https://www.ilias.de
 *      https://github.com/ILIAS-eLearning
 *
 *****************************************************************************/


class ilCSVWriter
{
    private string $csv = '';
    private string $separator = ',';
    private string $delimiter = '"';
    private string $new_line = "\n";
    private bool $do_utf8_decoding = false;
    private bool $first_entry = true;
    private bool $sep_directive = false;

    public function setSeparator(string $a_sep): void
    {
        $this->separator = $a_sep;
    }

    /**
     * Prepend a sep= directive so Excel auto-detects the separator.
     * Must be called before any addColumn()/addRow() calls.
     */
    public function setSepDirective(bool $enabled = true): void
    {
        $this->sep_directive = $enabled;
    }

    public function setDelimiter(string $a_del): void
    {
        $this->delimiter = $a_del;
    }

    public function addRow(): void
    {
        $this->csv .= $this->new_line;
        $this->first_entry = true;
    }

    public function setDoUTF8Decoding($do_utf8_decoding): void
    {
        $this->do_utf8_decoding = (bool) $do_utf8_decoding;
    }

    public function addColumn(string $a_col): void
    {
        if (!$this->first_entry) {
            $this->csv .= $this->separator;
        }
        $this->csv .= $this->delimiter;
        $this->csv .= $this->quote($a_col);
        $this->csv .= $this->delimiter;
        $this->first_entry = false;
    }

    public function getCSVString(): string
    {
        if ($this->sep_directive) {
            return 'sep=' . $this->separator . $this->new_line . $this->csv;
        }
        return $this->csv;
    }

    private function quote(string $a_str): string
    {
        // Replace newlines to prevent breaking CSV row structure
        $a_str = str_replace(["\r\n", "\r", "\n"], ' ', $a_str);
        return str_replace(
            $this->delimiter,
            $this->delimiter . $this->delimiter,
            ($this->do_utf8_decoding) ? utf8_decode($a_str) : $a_str
        );
    }
}
