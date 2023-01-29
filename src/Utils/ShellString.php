<?php
namespace Loco\Utils;

class ShellString {

  /**
   * Bare string. In `ls "My Docs"`, the 'ls' is parsed in bare mode.
   */
  const M_BARE = 'B';

  /**
   * Double-quoted string. In `ls "My Docs"`, the 'My Docs' is parsed in DBL mode.
   */
  const M_DBL = 'D';

  /**
   * Single-quoted string. In `ls 'My Docs'`, the 'My Docs' is parsed in SNG mode.
   */
  const M_SNG = 'S';

  const T_CHAR = 'c';
  const T_SPACE = ' ';
  const T_DBL = '"';
  const T_SNG = "'";
  const T_ESC = '\\';

  /**
   * Quick-and-dirty utility to split shell expressions into their parameters
   * (respecting quotes and backslashes).
   *
   * Note: For a more thorough approach, there's a rough take at a recursive
   * descent parser at https://gist.github.com/totten/1b06bd4be8992f38c749f633ebb3e464
   *
   * @param string $expr
   *   Shell-ish command expression
   *   Ex: 'ls -l "/tmp/foo bar" /tmp/funny\\biz'
   *   Ex: 'echo "Hello $name"'
   * @return string[]
   *   The same thing, split as a list of arguments. The control characters ' " \ are resolved.
   *   Variable expressions are preserved.
   *   Ex: ['ls', '-l', '/tmp/foo bar', '/tmp/funny\biz']
   *   Ex: ['echo', 'Hello $name']
   * @internal
   */
  public static function split(string $expr): array {
    $tokenTypes = [
      ' ' => static::T_SPACE,
      "\t" => static::T_SPACE,
      "\n" => static::T_SPACE,
      '"' => static::T_DBL,
      "'" => static::T_SNG,
      '\\' => static::T_ESC,
    ];

    // Accumulate a list of shell parts.
    $part = '';
    $parts = [];

    // State machine
    $mode = static::M_BARE;
    $chars = mb_str_split($expr);
    $char = NULL;
    $signature = '';

    // Utility to throw errors
    $fail = function () use (&$char, &$chars, &$signature, $expr) {
      $offset = mb_strlen($expr) - count($chars);
      throw new \RuntimeException("Parse error on \"$char\" (at $offset, sig \"$signature\")");
    };

    // Loop through chars.
    while (count($chars)) {
      $char = array_shift($chars);
      $tokenType = $tokenTypes[$char] ?? static::T_CHAR;
      $signature = $mode . $tokenType;
      switch ($signature) {
        case 'Bc': /* Bare mode, T_CHAR */
        case 'Sc': /* Single-quote mode, T_CHAR */
        case 'S ': /* Single-quote mode, T_SPACE */
        case 'S"': /* Single-quote mode, T_DBL */
        case 'Dc': /* Double-quote mode, T_CHAR */
        case 'D ':
        case "D'":
          $part .= $char;
          break;

        case 'B ':
          $parts[] = $part;
          $part = '';
          break;

        case 'B\\':
        case 'S\\':
        case 'D\\':
          if (count($chars)) {
            $part .= array_shift($chars);
          }
          break;

        case "B'":
          $mode = static::M_SNG;
          break;

        case 'B"':
          $mode = static::M_DBL;
          break;

        case 'D"':
        case "S'":
          $mode = static::M_BARE;
          break;

        default:
          $fail();
      }
    }

    if ($part !== '') {
      $parts[] = $part;
    }

    return $parts;
  }

}
