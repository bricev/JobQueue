<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue\Console;

use Libcast\JobQueue\Exception\CommandException;

class OutputTable
{
    const LEFT = 'left';

    const RIGHT = 'right';

    protected $columns = array();

    protected $rows = array();

    protected $row_style = array();

    protected $lenght = 0;

    public function addColumn($title, $width, $align)
    {
        $this->columns[$title] = array(
            'width' => max(array(strlen($title), $width)),
            'align' => $align,
        );
    }

    protected function countColumns()
    {
      return count($this->columns);
    }

    public function addRow(array $cells, $style = null)
    {
        if (count($cells) !== $this->countColumns()) {
            throw new CommandException('Number of cell is not valid.');
        }

        if (count(array_diff(array_keys($cells), $this->getColumnTitles()))) {
            throw new CommandException('One or more cells have a wrong title.');
        }

        foreach ($cells as $column_title => $cell_value) {
            if (!$cell_value || is_null($cell_value) || $cell_value === '') {
                $cells[$column_title] = '-';
            }

            if (strlen($column_title) > (int) $this->columns[$column_title]['width']) {
                $this->columns[$column_title]['width'] = strlen($column_title);
            }

            if (strlen($cell_value) > (int) $this->columns[$column_title]['width']) {
                $this->columns[$column_title]['width'] = strlen($cell_value);
            }
        }

        $this->rows[] = $cells;

        $this->row_style[] = $style ? $style : '#';
    }

    protected function countRows()
    {
        return count($this->rows);
    }

    protected function getColumnTitles()
    {
        return array_keys($this->columns);
    }

    protected function getColumnWidth($title)
    {
        return $this->columns[$title]['width'];
    }

    protected function getColumnAlign($title)
    {
        return $this->columns[$title]['align'];
    }

    public function getTable($with_style = false)
    {
        $strings = array();

        for ($i = -1; $i < $this->countRows(); $i++) {
            $strings[] = $this->getRow($i, $with_style);

            if ($i < 0) {
              $strings[] = $this->getLine();
            }
        }

        return $strings;
    }

    protected function getLine()
    {
        $line = str_repeat('-', $this->getTableWidth());

        $this->incrLenght(strlen($line));

        return $line;
    }

    protected function getRow($row = -1, $with_style = false)
    {
        $string = '';
        $count = 0;
        foreach ($this->getColumnTitles() as $column) {
            $count++;

            $value = $row < 0 ? $column : $this->getCell($column, $row);

            $string .= $this->padCell($value,
                    $this->getColumnWidth($column),
                    $this->getColumnAlign($column));

            if ($count < $this->countColumns()) {
                $string .= ' | ';
            }
        }

        $this->incrLenght(strlen($string));

        if ($with_style && $style = $this->getRowStyle($row)) {
            $string = "<$style>$string</$style>";

            $this->incrLenght(30);
        }

        return $string;
    }

    protected function getRowStyle($i)
    {
        if (!isset($this->row_style[$i]) || $this->row_style[$i] === '#') {
          return null;
        }

        return $this->row_style[$i];
    }

    protected function getCell($column, $row)
    {
        if (!isset($this->rows[$row][$column])) {
          throw new CommandException("Their is no value for column '$column', row '$row'.");
        }

        return $this->rows[$row][$column];
    }

    protected function getTableWidth()
    {
        $count = 0;

        foreach ($this->getColumnTitles() as $title) {
          $count += $this->getColumnWidth($title);
        }

        $count += $this->countColumns() * 3;

        return $count - 2;
    }

    protected function padCell($value, $width, $align = 'left')
    {
        $align = 'left' === $align ? STR_PAD_RIGHT : STR_PAD_LEFT;

        return str_pad($value, $width, ' ', $align);
    }

    protected function incrLenght($n)
    {
        $this->lenght += $n;
    }

    public function getLenght()
    {
        return $this->lenght;
    }
}
