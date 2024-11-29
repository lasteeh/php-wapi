<?php

namespace COre\Traits;

trait ManagesErrorTrait
{
  protected array $ERRORS = [];

  public function errors()
  {
    return $this->ERRORS;
  }

  public function add_error(string $message)
  {
    $this->ERRORS[] = $message;
  }

  public function clear_errors()
  {
    $this->ERRORS = [];
  }

  public function has_errors(): bool
  {
    return count($this->ERRORS) > 0;
  }
}
