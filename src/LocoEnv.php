<?php
namespace Loco;

use Loco\Expression\Experimental;

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
      [$key, $valExpr] = explode('=', $asgnExpr, 2);
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
  protected function evaluateSpec($spec, $onMissing = 'exception') {
    if (!$spec['isDynamic']) {
      return $spec['value'];
    }

    $lookupVar = function($name) use ($onMissing, $spec) {
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

    $evaluateArg = function($rawArg) use ($onMissing) {
      return $this->evaluate($rawArg, $onMissing);
    };

    return (new Experimental())->eval($spec['value'], $lookupVar, $evaluateArg);
  }

}
