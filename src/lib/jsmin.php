<?php
/**
    Prails Web Framework
    Copyright (C) 2012  Robert Kunze

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

class JSMin
{
    const ORD_LF = 10;
    const ORD_SPACE = 32;

    protected $a = '';
    protected $b = '';
    protected $input = '';
    protected $inputIndex = 0;
    protected $inputLength = 0;
    protected $lookAhead = null;
    protected $output = '';

    public static function minify($js)
    {
        $jsmin = new JSMin($js);
        return $jsmin->min();
    }

    public function __construct($input)
    {
        $this->input = str_replace("\r\n", "\n", $input);
        $this->inputLength = strlen($this->input);
    }

    protected function action($d)
    {
        switch($d)
        {
            case 1:
                $this->output .= $this->a;

            case 2:
                $this->a = $this->b;

                if ($this->a === "'" || $this->a === '"')
                {
                    for (; ; )
                    {
                        $this->output .= $this->a;
                        $this->a = $this->get();

                        if ($this->a === $this->b)
                        {
                            break;
                        }

                        if (ord($this->a) <= self::ORD_LF)
                        {
                            throw new JSMinException('Unterminated string literal.');
                        }

                        if ($this->a === '\\')
                        {
                            $this->output .= $this->a;
                            $this->a = $this->get();
                        }
                    }
                }

            case 3:
                $this->b = $this->next();

                if ($this->b === '/' && (
                $this->a === '(' || $this->a === ',' || $this->a === '=' ||
                $this->a === ':' || $this->a === '[' || $this->a === '!' ||
                $this->a === '&' || $this->a === '|' || $this->a === '?'))
                {

                    $this->output .= $this->a.$this->b;

                    for (; ; )
                    {
                        $this->a = $this->get();

                        if ($this->a === '/')
                        {
                            break;
                        } elseif ($this->a === '\\')
                        {
                            $this->output .= $this->a;
                            $this->a = $this->get();
                        } elseif (ord($this->a) <= self::ORD_LF)
                        {
                            throw new JSMinException('Unterminated regular expression '.
                            'literal.');
                        }

                        $this->output .= $this->a;
                    }

                    $this->b = $this->next();
                }
        }
    }

    protected function get()
    {
        $c = $this->lookAhead;
        $this->lookAhead = null;

        if ($c === null)
        {
            if ($this->inputIndex < $this->inputLength)
            {
                $c = $this->input[$this->inputIndex];
                $this->inputIndex += 1;
            } else
            {
                $c = null;
            }
        }

        if ($c === "\r")
        {
            return "\n";
        }

        if ($c === null || $c === "\n" || ord($c) >= self::ORD_SPACE)
        {
            return $c;
        }

        return ' ';
    }

    protected function isAlphaNum($c)
    {
        return ord($c) > 126 || $c === '\\' || preg_match('/^[\w\$]$/', $c) === 1;
    }

    protected function min()
    {
        $this->a = "\n";
        $this->action(3);

        while ($this->a !== null)
        {
            switch($this->a)
            {
                case ' ':
                    if ($this->isAlphaNum($this->b))
                    {
                        $this->action(1);
                    } else
                    {
                        $this->action(2);
                    }
                break;

                case "\n":
                    switch($this->b)
                    {
                        case '{':
                        case '[':
                        case '(':
                        case '+':
                        case '-':
                            $this->action(1);
                            break;

                        case ' ':
                            $this->action(3);
                            break;

                        default:
                            if ($this->isAlphaNum($this->b))
                            {
                                $this->action(1);
                            }
                            else
                            {
                                $this->action(2);
                            }
                    }
                    break;

                default:
                    switch($this->b)
                    {
                        case ' ':
                            if ($this->isAlphaNum($this->a))
                            {
                                $this->action(1);
                                break;
                            }


                            $this->action(3);
                        break;

                        case "\n":
                            switch($this->a)
                            {
                                case '}':
                                case ']':
                                case ')':
                                case '+':
                                case '-':
                                case '"':
                                case "'":
                                    $this->action(1);
                                    break;

                                default:
                                    if ($this->isAlphaNum($this->a))
                                    {
                                        $this->action(1);
                                    }
                                    else
                                    {
                                        $this->action(3);
                                    }
                            }
                            break;

                        default:
                            $this->action(1);
                            break;
                }
        }
    }

    return $this->output;
}

protected function next()
{
    $c = $this->get();

    if ($c === '/')
    {
        switch($this->peek())
        {
            case '/':
                for (; ; )
                {
                    $c = $this->get();

                    if (ord($c) <= self::ORD_LF)
                    {
                        return $c;
                    }
                }

            case '*':
                $this->get();

                for (; ; )
                {
                    switch($this->get())
                    {
                        case '*':
                            if ($this->peek() === '/')
                            {
                                $this->get();
                                return ' ';
                            }
                        break;

                        case null:
                            throw new JSMinException('Unterminated comment.');
                    }
                }

            default:
                return $c;
        }
    }

    return $c;
}

protected function peek()
{
    $this->lookAhead = $this->get();
    return $this->lookAhead;
}
}

class JSMinException extends Exception
{
}
?>
