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
      $merged->specs = array_merge($merged->specs, $locoEnv->specs);
    }
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
    return $env;
  }

  public function set($key, $value, $isDynamic = FALSE) {
    $this->specs[$key] = [
      'value' => $value,
      'isDynamic' => $isDynamic,
    ];
    return $this;
  }

  public function getSpec($key) {
    return $this->specs[$key] ?: NULL;
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
        'value' => $this->evaluate($this->specs[$key]['value']),
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

  public function evaluate($valExpr, $onMissing = 'exception') {
    if (empty($valExpr)) {
      return $valExpr;
    }
    return preg_replace_callback(';\$([a-zA-Z0-9_\{\}]+);', function($matches) use ($valExpr, $onMissing) {
      $name = $matches[1];
      if (preg_match(';^[a-zA-Z0-9_]+$;', $name, $m2)) {
        $v2 = $this->getValue($name, $onMissing);
        return $v2;
      }
      elseif (preg_match(';^\{([a-zA-Z0-9_]+)\}$;', $name, $m2)) {
        $v2 = $this->getValue($m2[1], $onMissing);
        return $v2;
      }
      else {
        throw new \RuntimeException("Malformed variable expression: " . $name);
      }
    }, $valExpr);
  }

}
