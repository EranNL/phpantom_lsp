# PHPantom — Bug Fixes

Every bug below must be fixed at its root cause. "Detect the
symptom and suppress the diagnostic" is not an acceptable fix.
If the type resolution pipeline produces wrong data, fix the
pipeline so it produces correct data. Downstream consumers
(diagnostics, hover, completion, definition) should never need
to second-guess upstream output.

Each entry below carries an **Impact · Complexity** rating using the same
scale defined in [`docs/todo.md`](../todo.md); that table is also where
each bug's row lives in the current sprint/backlog.

Bugs land here from wherever they surface: found while working on another
task, or sweeps of the sample projects under `projects/`. Entries are
grouped by the mechanism that has to change, not by the symptom that
surfaced: one entry is one root cause, however many shapes it shows up in.

## Crashes

No outstanding items.

## Type comparison

No outstanding items.

## Standard-library return types

No outstanding items.

## Reachability

### B493. A `catch` the try body cannot reach is still merged into `finally`
**Impact: Low · Complexity: Medium-High**

```php
try {
    $s = 1;
    $this->throwsLogicException(); // @throws LogicException
} catch (\RuntimeException $e) {
    $s = 'bar';
} finally {
    $s; // should be 1, is 1|'bar'
}
```

Telling that a catch is dead needs the exceptions the try body can throw, read from the `@throws` of what it calls.

Found porting PHPStan's `nsrt/finally-scope.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

## Narrowing

### B487. An impure call does not forget what was proved about a static property
**Impact: Low · Complexity: Medium**

```php
private static int|float $i;
public function __construct() {
    self::$i = getInt();
    $this->impureCall(); // @phpstan-impure
    self::$i; // should be float|int, is int
}
```

`process_receiver_mutation` invalidates what is known through the receiver (`$this->…`), but any impure call can write a static property, so `self::$x`, `static::$x`, `parent::$x` and `Foo::$x` keys have to go too.

Found porting PHPStan's `nsrt/bug-12902.php` and `nsrt/bug-12902-non-strict.php`; the assertions are `// SKIP` in the ported copies under `tests/phpstan_nsrt/`.

### B488. A call proved through `?->` stays narrowed under its `->` spelling when the method is impure
**Impact: Low · Complexity: Low-Medium**

```php
if ($bar?->getImpure() !== null) { // @phpstan-impure, returns ?int
    $bar->getImpure();  // should be int|null, is int
    $bar?->getImpure(); // int|null (correct)
}
```

The nullsafe spelling of the call is forgotten as an impure result, but the `->` spelling the condition also proves is kept.

Found porting PHPStan's `nsrt/bug-4757.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B489. A comparison does not narrow a union of float literals
**Impact: Low · Complexity: Medium**

```php
$x = 0.0;
if ($y > 0) { $x += 1; }
if ($x > 0) {
    $x; // should be 1.0, is 0.0|1.0
}
```

Integer literal unions are narrowed by `<`, `>`, `<=` and `>=`; float literals are left as they were.

Found porting PHPStan's `nsrt/bug-5309.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B509. A false `strlen() > 0` guard does not narrow the string to `''`
**Impact: Low · Complexity: Medium**

```php
function f(string $s) {
    if (strlen($s) > 0) {
        return;
    }
    $s;         // should be '', is string
    strlen($s); // should be 0, is int<0, max>
}
```

A comparison on the length of a string says what the string is: a length of zero is the empty string, a positive one is `non-empty-string`.

Found porting PHPStan's `nsrt/bug-5129.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B490. `count()` does not size a shape with explicit keys and optional entries
**Impact: Low · Complexity: Medium**

```php
/** @param array{0: mixed, 1?: string|null} $row */
function f(array $row) {
    if (count($row) === 1) {
        $row; // should be array{mixed}, is array{0: mixed, 1?: string|null}
    } else {
        $row; // should be array{mixed, string|null}
    }
}
```

`list_of_size` gives up on a shape whose entries spell out their keys, even when the keys are the sequential ones a list has. The branch where the sizes differ learns nothing either, although it rules out the size the check named.

Found porting PHPStan's `nsrt/list-count.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B491. The receiver of a nullsafe call is not narrowed inside its arguments
**Impact: Low · Complexity: Low-Medium**

```php
function f(?\Exception $e) {
    $e?->getMessage(g($e)); // $e should be Exception inside the arguments, is ?Exception
}
```

The arguments are only evaluated when the receiver is not null.

Found porting PHPStan's `nsrt/nullsafe.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B492. What the constructor proves about a readonly property of a readonly property is not remembered
**Impact: Low · Complexity: Medium**

```php
public readonly ?Foo $prop; // Foo has `public readonly int $readonly`
public function __construct() {
    $this->prop = new Foo();
    if ($this->prop->readonly != 5) { throw new LogicException(); }
}
public function doFoo() {
    $this->prop->readonly; // should be 5, is int
}
```

`seed_constructor_readonly_properties` carries the class's own readonly properties into the other methods. A path through two readonly properties cannot change either, so it can be carried too.

Found porting PHPStan's `nsrt/remember-readonly-constructor-narrowed.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B507. An assertion on a value typed as a union of classes and `null` leaves the `null`
**Impact: Low-Medium · Complexity: Low-Medium**

```php
class A { /** @return A|B|null */ public function abn() {} }
/** @psalm-assert A $v */
function assertA($v): void {}

$x = $a->abn();
assertA($x);
$x; // should be A, is A|null
```

The value resolves to one entry per class plus a separate `null` entry, and the assertion narrows the class entries without dropping the `null` one. A single class (`?A`) carries the `null` inside its own entry and narrows correctly. PHPUnit's `assertInstanceOf()` goes through the same path.

## Arithmetic

No outstanding items.

## Symbol resolution

No outstanding items.

## Array types

No outstanding items.

## Laravel

No outstanding items.

## Blade

No outstanding items.

## Templates

No outstanding items.

## Miscellaneous

### B508. A closure that captures its own variable by reference is typed `null` inside its body
**Impact: Medium · Complexity: Low-Medium**

```php
$callback = function () use (&$callback, $loop): void {
    $loop->addTimer(1.0, $callback); // reported: expects callable, got null
};
```

PHP creates `$callback` as `null` when the closure literal runs, but the assignment stores the closure into it before the body can ever execute, so inside the body it is the closure. Treating the capture as `null` is right only when nothing assigns the variable afterwards. This appeared with the change that types an undefined by-reference capture as `null`, and shows as three false positives in phpstan-src (`src/Command/FixerApplication.php`, `monitorFileChanges()`).

### B510. An overriding method's narrower return type is lost on some call sites
**Impact: Medium · Complexity: Unknown**

In pdepend, `ASTMethod::getParent(): ?AbstractASTClassOrInterface` overrides `AbstractASTArtifact::getParent(): ?ASTNode`, yet `$method->getParent()` resolves to the parent's `?ASTNode` at eight call sites (`ClassDependencyAnalyzer.php`, `CodeRankAnalyzer/MethodStrategy.php`, `CouplingAnalyzer.php`, `ASTParameter.php`), plus one in phpmd (`Rule/CleanCode/UndefinedVariable.php`). These all came back in the 2026-09-27 sweep and are present at `063275b0`, so one of that day's earlier commits introduced them. A reduced three-level hierarchy with the same shape resolves correctly, so the trigger has not been isolated yet; bisecting the day's commits against `analyze` on pdepend is the quickest way in.
