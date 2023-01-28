<?php

namespace Loco;

use Loco\Utils\ShellString;

class LocoEvaluator {

  /**
   * @var callable[]|null
   */
  protected $functions = NULL;

  /**
   * @param string|null $valExpr
   *   The user-supplied expression to evaluate.
   * @param callable $lookupVar
   *   A function to lookup values of variables.
   * @return string|null
   *   User-supplied expression, with variables replaced.
   */
  public function evaluate(?string $valExpr, callable $lookupVar): ?string {
    if (empty($valExpr)) {
      return $valExpr;
    }

    $varExprRegex = '\$([a-zA-Z0-9_]+|{[a-zA-Z0-9_]+})'; // Ex: '$FOO' or '${FOO}'
    $funcNameRegex = '[a-zA-Z-9_]+'; // Ex: 'basename' or 'dirname'
    $funcExprRegex = '\$\((' . $funcNameRegex . ')([^()]*)\)'; // Ex: '$(basename $FOO)'

    return preg_replace_callback(';(' . $funcExprRegex . '|' . $varExprRegex . ');', function($mainMatch) use ($valExpr, $varExprRegex, $funcExprRegex, $lookupVar) {
      if (preg_match(";^$varExprRegex$;", $mainMatch[1], $matches)) {
        $name = preg_replace(';^\{(.*)\}$;', '\1', $matches[1]);
        return call_user_func($lookupVar, $name);
      }
      elseif (preg_match(";^$funcExprRegex$;", $mainMatch[1], $matches)) {
        $func = $matches[1];
        $rawArgs = ShellString::split(trim($matches[2]));
        $args = [];
        foreach ($rawArgs as $rawArg) {
          $args[] = $this->evaluate($rawArg, $lookupVar);
        }

        return $this->callFunction($func, ...$args);
      }

      throw new \RuntimeException("Malformed variable expression: " . $mainMatch[0]);
    }, $valExpr);
  }

  /**
   * Get a list of functions.
   *
   * For example, in the expression "cp foo $(dirname $BAR)", the "dirname" is a function.
   *
   * @return array
   *   Ex: ['basename' => function(string $one, string $two, ...): string]
   * @experimental
   */
  public function callFunction(string $function, ...$args): string {
    if ($this->functions === NULL) {
      $data = Loco::filter('loco.expr.functions', ['functions' => []]);
      $this->functions = $data['functions'];
    }

    if (isset($this->functions[$function])) {
      return call_user_func_array($this->functions[$function], $args);
    }
    else {
      throw new \RuntimeException("Invalid function: " . $function);
    }
  }

}
