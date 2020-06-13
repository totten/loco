<?php
namespace Loco;

class LocoEnv {

  protected $specs = [];

  /**
   * @param array $locoEnvs
   * @return LocoEnv
   */
  public static function merge($locoEnvs) {
    $merged = new static();
    foreach ($locoEnvs as $locoEnv) {
      foreach ($locoEnv->specs as $envKey => $envSpec) {
        if (!isset($merged->specs[$envKey])) {
          $merged->specs[$envKey] = $envSpec;
        }
        else {
          $envSpec['parent'] = $merged->specs[$envKey];
          $merged->specs[$envKey] = $envSpec;
        }
      }
    }

    Loco::filter('loco.env.merge', ['env' => $merged, 'srcs' => $locoEnvs]);
    return $merged;
  }

  /**
   * @param array $asgnExprs
   *   Ex: ['FOO=123', 'BAR=abc_$FOO']
   * @return LocoEnv
   */
  public static function create($asgnExprs) {
    $env = new static();
    foreach ($asgnExprs as $asgnExpr) {
      list ($key, $valExpr) = explode('=', $asgnExpr, 2);
      $env->set($key, $valExpr, TRUE);
    }
    Loco::filter('loco.env.create', ['env' => $env, 'assignments' => $asgnExprs]);
    return $env;
  }

  public function set($key, $value, $isDynamic = FALSE) {
    $this->specs[$key] = [
      'name' => $key,
      'value' => $value,
      'isDynamic' => $isDynamic,
    ];
    return $this;
  }

  public function getSpec($key) {
    return $this->specs[$key] ?? NULL;
  }

  public function getValue($key, $onMissing = 'exception') {
    if (!isset($this->specs[$key])) {
      switch ($onMissing) {
        case 'keep':
          return '$' . $key;

        case 'null':
          return NULL;

        case 'exception':
        default:
          throw new \RuntimeException("Unknown variable: $key");
      }

    }
    if ($this->specs[$key]['isDynamic']) {
      $this->specs[$key] = [
        'name' => $key,
        'value' => $this->evaluateSpec($this->specs[$key], 'exception'),
        'isDynamic' => FALSE,
      ];
    }
    return $this->specs[$key]['value'];
  }

  /**
   * @return array
   *   List of variable names
   */
  public function getKeys() {
    return array_keys($this->specs);
  }

  public function getAllValues() {
    $values = [];
    foreach ($this->specs as $key => $spec) {
      $values[$key] = $this->getValue($key);
    }
    return $values;
  }

  /**
   * @param string $valExpr
   *   Ex: '$FOO/bar'
   * @param string $onMissing
   *   Ex: 'exception', 'null', 'keep'
   * @return string|NULL
   */
  public function evaluate($valExpr, $onMissing = 'exception') {
    $spec = [
      'name' => NULL,
      'value' => $valExpr,
      'isDynamic' => TRUE,
    ];
    return $this->evaluateSpec($spec, $onMissing);
  }

  /**
   * @param array $spec
   *   Ex: ['name' => 'PATH', 'value' => '/foo/bar:$PATH', 'isDynamic' => TRUE]
   * @param string $onMissing
   *   Ex: 'exception', 'null', 'keep'
   * @return string|NULL
   */
  public function evaluateSpec($spec, $onMissing = 'exception') {
    if (!$spec['isDynamic']) {
      return $spec['value'];
    }
    $valExpr = $spec['value'];

    if (empty($valExpr)) {
      return $valExpr;
    }

    $varExprRegex = '\$([a-zA-Z0-9_\{\}]+)'; // Ex: '$FOO' or '${FOO}'
    $funcNameRegex = '[a-zA-Z-9_]+'; // Ex: 'basename' or 'dirname'
    $funcExprRegex = '\$\((' . $funcNameRegex .') (' . $varExprRegex . ')\)'; // Ex: '$(basename $FOO)'

    $lookupVar = function($name) use ($onMissing, $spec) {
      $name = preg_replace(';^\{(.*)\}$;', '\1', $name);
      if ($name === $spec['name']) {
        // Recursive value expression! Consult parent environment.
        return isset($spec['parent'])
          ? $this->evaluateSpec($spec['parent'], $onMissing)
          : '';
      }
      elseif (preg_match(';^[a-zA-Z0-9_]+$;', $name)) {
        return $this->getValue($name, $onMissing);
      }
    };

    return preg_replace_callback(';(' . $funcExprRegex . '|' . $varExprRegex . ');', function($mainMatch) use ($valExpr, $onMissing, $varExprRegex, $funcExprRegex, $spec, $lookupVar) {
      if (preg_match(";^$varExprRegex$;", $mainMatch[1], $matches)) {
        return $lookupVar($matches[1]);
      }
      elseif (preg_match(";^$funcExprRegex$;", $mainMatch[1], $matches)) {
        $target = $lookupVar($matches[3]);
        $func = $matches[1];
        switch ($func) {
          case 'dirname':
            return dirname($target);

          case 'basename':
            return basename($target);

          default:
            throw new \RuntimeException("Invalid function expression: " . $valExpr);
        }
      }

      throw new \RuntimeException("Malformed variable expression: " . $mainMatch[0]);
    }, $valExpr);
  }

}
