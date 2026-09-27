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

### B481. An element function drops the `false` or `null` an empty array gives
**Impact: Medium · Complexity: Medium**

```php
/** @param \stdClass[] $objects */
function f(array $objects): void {
    reset($objects);     // should be stdClass|false, is stdClass
    reset([]);           // should be false, is mixed|false
    array_shift($objects); // should be stdClass|null, is stdClass
}
```

`reset()`, `end()`, `current()`, `next()`, `prev()` return `false` and `array_pop()`, `array_shift()`, `array_first()`, `array_last()` return `null` when the array has no entries. `array_func_element_type` answers the element type alone, so the sentinel is only right for an array that is provably non-empty (a literal with entries). Adding it back will surface new argument-type diagnostics where a result is passed on unchecked, so re-run `analyze` on the sample projects before and after.

Found porting PHPStan's `nsrt/array-pointer-functions.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B482. Key functions widen the literal keys of an array literal
**Impact: Low · Complexity: Low-Medium**

```php
$a = [2 => 1, 3 => 2, 4 => 1];
array_keys($a);     // should be array{2, 3, 4}, is list<int>
array_keys($a, 1);  // should be list<2|3|4>, is list<int>
array_key_last(rand(0, 1) ? [] : ['one', 'two']); // should be 0|1|null, is int|null
```

The key template is bound from the shape's widened key type. A declared `array<1|2|3, V>` keeps its literal keys, so only the array literal's own keys are lost.

Found porting PHPStan's `nsrt/bug-11928.php` and `nsrt/bug-14081.php`; the assertions are `// SKIP` in the ported copies under `tests/phpstan_nsrt/`.

### B483. `preg_replace()` over an array shape loses its keys
**Impact: Low · Complexity: Medium**

```php
/** @param array{a: string, b: string} $arr */
function f(array $arr) {
    preg_replace('/^a/', 'x', $arr); // should be array{a?: string, b?: string}, is array<array-key, string>
}
```

Given an array subject, `preg_replace()` returns the subject's keys (each optional, since an entry whose replacement fails is dropped) with string values.

Found porting PHPStan's `nsrt/bug-11547.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B484. `array_merge()` of array literals does not produce the merged shape
**Impact: Low-Medium · Complexity: Medium**

```php
array_merge(['foo' => 1, 'bar' => 2], [2, 3]); // should be array{foo: 1, bar: 2, 0: 2, 1: 3}, is array
```

Merging known shapes has a known result: string keys from later arrays overwrite earlier ones and integer keys are renumbered. Array key completion on the result is where this shows.

Found porting PHPStan's `nsrt/array-merge2.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B485. `array_shift()` does not drop the first entry of a list shape
**Impact: Low · Complexity: Medium**

```php
/** @param list<int> $items */
function f(array $items) {
    if (count($items) === 3) {
        array_shift($items);
        $items; // should be array{int, int}, is array{int, int, int}
    }
}
```

The by-reference write leaves the argument as it was. A list shape loses its first entry and the rest move down one key.

Found porting PHPStan's `nsrt/list-count.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B486. `strlen()` of a known literal string is not folded
**Impact: Low · Complexity: Low**

```php
$this->foo = '';
strlen($this->foo); // should be 0, is int<0, max>
```

A literal argument has a known length, the same way the other scalar folds read one.

Found porting PHPStan's `nsrt/bug-5129.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

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

## Arithmetic

No outstanding items.

## Symbol resolution

### B494. A nullsafe access does not add `null` for a nullable receiver
**Impact: Medium · Complexity: Low-Medium**

```php
function f(?\Exception $e) {
    $e?->getMessage(); // should be string|null, is string
}
$null = null;
$null?->foo;           // should be null, has no type
```

A `?->` call or property read short-circuits to `null` when its receiver is null, so its type is the member's type plus `null` whenever the receiver may be null, and `null` alone when it always is. Like B481 this will surface new argument-type diagnostics, so re-run `analyze` on the sample projects.

Found porting PHPStan's `nsrt/nullsafe.php` and `nsrt/bug-4757.php`; the assertions are `// SKIP` in the ported copies under `tests/phpstan_nsrt/`.

### B495. A method on an intersection returns the union of its members' return types
**Impact: Low · Complexity: Medium**

```php
/** @var WithFoo&WithFooAndBarInterface $x */
$x->doFoo(); // should be Foo&AnotherFoo, is Foo|AnotherFoo
```

A value that is both types satisfies both declarations, so the result is both return types at once. A union is right for a union receiver, not an intersection.

Found porting PHPStan's `nsrt/union-intersection.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B496. A static call on an intersection-typed variable or on a property has no type
**Impact: Low-Medium · Complexity: Medium**

```php
/** @var WithFoo&SomeInterface $foo */
$foo::doStaticFoo();         // should be Foo, has no type
$this->union::doStaticFoo(); // should be Foo|AnotherFoo, has no type
```

`resolve_rhs_static_call` reads the class from a variable's first `base_name()`, which an intersection does not have, and does not read it from any other expression.

Found porting PHPStan's `nsrt/union-intersection.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

## Array types

### B497. A literal written under a non-literal key is widened on the loop's next pass
**Impact: Low · Complexity: Medium**

```php
$out = [];
foreach (['foof' => 'barr', 'ftt' => []] as $k => $b) {
    $out[$k]; // should be 'toto'|array{}, is string|array{}
    if (is_array($b)) { $out[$k] = []; } else { $out[$k] = 'toto'; }
}
```

The write itself keeps `'toto'`, so the widening happens when the loop body's exit state is merged back into its entry.

Found porting PHPStan's `nsrt/bug-1516.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

## Laravel

No outstanding items.

## Blade

No outstanding items.

## Templates

### B463. A method template with a bound is shown as its bound inside the method
**Impact: Low · Complexity: High**

```php
/** @template T of A&B @param iterable<T> $items */
function f($items) {
    foreach ($items as $item) {
        $item; // should be T, is A&B
    }
}
```

An unbounded method template, and a class template, keep their name inside the body (`T`); a method template with a bound is replaced by the bound when the parameter is seeded (`substitute_template_param_bounds` and `substitute_class_string_template_bounds`, called from `resolve_param_type` in `forward_walk/param_seeding.rs`). The value is still `T`, so hover should show `T`, and a value returned or passed on should carry the template rather than the bound. A method template bounded by a class template (`@template F of E`) shows the class template's name `E` instead of `F`.

Dropping the substitution alone is not a fix, because the substitution is what gives these values their members. A class template named in a type resolves to classes through its owning class's bounds (the fallback at the end of `type_hint_to_classes_typed_depth` in `type_engine/types/resolution.rs`). Nothing records a method template's bound where a later lookup can reach it, so with the bare name, member access on anything derived from the parameter stops working: `$item->` in the example above, `$res[$position]->` for an `array<T>`. Some of those lookups run after the body walk has finished (completion re-resolving `array<T>`'s element), so a thread-local pushed while the walk is running does not cover them either.

What needs doing: make the bound travel with the type. The likely shape is a new `TypeKind` for a bounded template parameter (name plus bound), modelled on how `StaticType` carries its class:

- It displays as the template name, so hover and the assertion runner see `T`.
- It resolves to classes, and is judged by subtyping and narrowing, as its bound (`is_string($t)` on `T of int|string` narrows like the bound does).
- `substitute()` replaces it by name like `Named`, so call-site template substitution keeps working.
- Parameter seeding produces it instead of substituting the bound.

Every `match` on `TypeKind` needs checking for the new arm; the ones that list `StaticType` explicitly (about 15 files) are the place to start.

Done when the `// SKIP`'d assertions below pass, completion and unknown-member diagnostics on bounded method template values behave as they do today, and the full suite passes.

Found porting PHPStan's `Rules/Methods/data/bug-7511.php`, `Rules/Methods/data/bug-5562.php`, `Rules/Generics/data/bug-3769.php`, `Rules/PhpDoc/data/bug-4643.php` and `Rules/Functions/data/bug-7823.php`; the assertions are `// SKIP` in the ported copies under `tests/phpstan_data/`. `nsrt/conditional-types.php`, `nsrt/nested-generic-types-unwrapping.php` and `nsrt/nested-generic-types-unwrapping-covariant.php` show it too, under `tests/phpstan_nsrt/`.

### B500. A key template bound from an array that names only its value type binds the whole array
**Impact: Low · Complexity: Medium**

```php
/** @template T of array-key @param array<T, mixed> $one @return T */
function uksort(array $one) {}
/** @var Foo[] $items */
uksort($items); // should be int|string, is array<Foo>
```

`Foo[]` has the implicit key type `array-key`, which is what `T` binds to.

Found porting PHPStan's `nsrt/uksort-bug.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B501. An assertion on `$this` that names a generic class loses the template arguments
**Impact: Low · Complexity: Medium**

```php
/** @template TOk @template TErr */
interface Result {
    /** @phpstan-assert-if-true Ok<TOk> $this */
    public function isOk(): bool;
}
/** @var Result<int, string> $result */
if ($result->isOk()) {
    $result; // should be Ok<int>, is Ok
}
```

The narrowing picks the right class and its members resolve (`$result->unwrap()` is `int`), but the asserted type's own template arguments are not substituted with the receiver's.

Found porting PHPStan's `nsrt/assert-this.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B502. A global constant as a conditional type's target is not resolved to its value
**Impact: Low · Complexity: Low-Medium**

```php
/** @return ($flag is PREG_SPLIT_NO_EMPTY ? true : false) */
function f(int $flag): bool {}
f(PREG_SPLIT_NO_EMPTY); // should be true, is false
```

The target names a constant, which has to be read as its value (`1`) before the argument can be compared with it.

Found porting PHPStan's `nsrt/conditional-types-constant.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B503. A conditional on a name that is not a parameter picks the else branch
**Impact: Low · Complexity: Low**

```php
/** @return ($parameter is true ? int : string) */
function f() {}
f(); // should be int|string, is string
```

Nothing is known about a name the signature does not declare, so both branches remain.

Found porting PHPStan's `nsrt/conditional-types.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B504. A conditional type whose subject is a literal is not evaluated
**Impact: Low · Complexity: Low-Medium**

```php
/** @return (5 is int ? true : false) */
function f() {}
f(); // should be true, is false

/** @param (true is true ? string : bool) $foo */
function g($foo) {
    $foo; // should be string, is shown as the raw conditional
}
```

A literal subject decides the condition without any argument.

Found porting PHPStan's `nsrt/conditional-types.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B505. A template bound through another template's bound from an empty array does not bind `never`
**Impact: Low · Complexity: Medium**

```php
/**
 * @template TKey of array-key
 * @template TArray of array<TKey, mixed>
 * @param TArray $array
 * @return (TArray is non-empty-array ? non-empty-list<TKey> : list<TKey>)
 */
function arrayKeys(array $array) {}
arrayKeys([]); // should be list<never>, is list<array-key>
```

`TArray` binds to `array{}`, but `TKey` is only reached through `TArray`'s bound, and an empty array leaves it on its own bound instead of `never`. A template bound directly from an empty array (`@param iterable<T>` handed `[]`) already binds `never`.

Found porting PHPStan's `nsrt/conditional-types.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

## Miscellaneous

### B498. A variable first introduced by a by-reference closure `use` is not typed `null`
**Impact: Low · Complexity: Low**

```php
$callback = function () use (&$untouched) {
    $untouched; // should be null, has no type
};
$untouched;     // should be null, has no type
```

Capturing an undefined variable by reference creates it, as `null`, in both scopes.

Found porting PHPStan's `nsrt/closure-passed-by-reference.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

### B499. `array_map()` with a closure that rewrites its parameter keeps the parameter's shape
**Impact: Low-Medium · Complexity: Medium**

```php
/** @var list<array{a: int}> $results */
array_map(static function (array $result): array {
    $result['a'] = (string) $result['a'];
    return $result;
}, $results); // should be list<array{a: string}>, is list<array{a: int}>
```

The closure's return type is read as its parameter's inferred type rather than from what the body returns after the write.

Found porting PHPStan's `nsrt/bug-4587.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.
