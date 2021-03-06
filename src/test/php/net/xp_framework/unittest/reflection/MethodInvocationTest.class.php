<?php namespace net\xp_framework\unittest\reflection;

use lang\IllegalAccessException;
use lang\IllegalArgumentException;
use lang\reflect\TargetInvocationException;
use unittest\actions\RuntimeVersion;

class MethodInvocationTest extends MethodsTest {

  #[@test]
  public function invoke_instance() {
    $fixture= $this->type('{ public function fixture() { return "Test"; } }');
    $this->assertEquals('Test', $fixture->getMethod('fixture')->invoke($fixture->newInstance(), []));
  }

  #[@test]
  public function invoke_static() {
    $fixture= $this->type('{ public static function fixture() { return "Test"; } }');
    $this->assertEquals('Test', $fixture->getMethod('fixture')->invoke(null, []));
  }

  #[@test]
  public function invoke_passes_arguments() {
    $fixture= $this->type('{ public function fixture($a, $b) { return $a + $b; } }');
    $this->assertEquals(3, $fixture->getMethod('fixture')->invoke($fixture->newInstance(), [1, 2]));
  }

  #[@test]
  public function invoke_method_without_return() {
    $fixture= $this->type('{ public function fixture() { } }');
    $this->assertNull($fixture->getMethod('fixture')->invoke($fixture->newInstance(), []));
  }

  #[@test, @expect(TargetInvocationException::class)]
  public function cannot_invoke_instance_method_without_object() {
    $fixture= $this->type('{ public function fixture() { } }');
    $fixture->getMethod('fixture')->invoke(null, []);
  }

  #[@test, @expect(TargetInvocationException::class)]
  public function exceptions_raised_during_invocation_are_wrapped() {
    $fixture= $this->type('{ public function fixture() { throw new \lang\IllegalAccessException("Test"); } }');
    $fixture->getMethod('fixture')->invoke($fixture->newInstance(), []);
  }

  #[@test, @expect(TargetInvocationException::class), @action(new RuntimeVersion('>=7.0'))]
  public function exceptions_raised_for_return_type_violations() {
    $fixture= $this->type('{ public function fixture(): array { return null; } }');
    $fixture->getMethod('fixture')->invoke($fixture->newInstance(), []);
  }

  #[@test, @expect(IllegalArgumentException::class)]
  public function cannot_invoke_instance_method_with_incompatible() {
    $fixture= $this->type('{ public function fixture() { } }');
    $fixture->getMethod('fixture')->invoke($this, []);
  }

  #[@test, @expect(IllegalAccessException::class), @values([
  #  ['{ private function fixture() { } }'],
  #  ['{ protected function fixture() { } }'],
  #  ['{ public abstract function fixture(); }', 'abstract'],
  #])]
  public function cannot_invoke_non_public($declaration, $modifiers= '') {
    $fixture= $this->type($declaration, $modifiers);
    $fixture->getMethod('fixture')->invoke($fixture->newInstance(), []);
  }

  #[@test, @values([
  #  ['{ private function fixture() { return "Test"; } }'],
  #  ['{ protected function fixture() { return "Test"; } }'],
  #])]
  public function can_invoke_private_or_protected_via_setAccessible($declaration) {
    $fixture= $this->type($declaration);
    $this->assertEquals('Test', $fixture->getMethod('fixture')->setAccessible(true)->invoke($fixture->newInstance(), []));
  }
}