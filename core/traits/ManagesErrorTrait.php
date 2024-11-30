<?php

namespace Core\Traits;

trait ManagesErrorTrait
{
  protected array $ERRORS = [];

  public function errors()
  {
    return $this->ERRORS;
  }

  public function add_errors(array $errors)
  {
    foreach ($errors as $error) {
      $this->add_error($error);
    }
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
