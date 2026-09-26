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

No outstanding items.

## Narrowing

No outstanding items.

## Arithmetic

No outstanding items.

## Symbol resolution

### B484. `extract()` with flags or a non-shape array leaves the locals untouched
**Impact: Low · Complexity: Medium**

```php
function f(array $data) {
    $x = 1;
    extract($data);
    $x; // should be mixed, is 1
    extract(['y' => 's'], EXTR_PREFIX_ALL, 'p');
    $p_y; // should be 's', resolves to nothing
}
```

Only a single-argument `extract()` of an array shape defines its keys. An argument whose keys are not known may overwrite any local with anything, so every variable in scope should widen to `mixed` after the call (PHPStan's `afterExtractCall()`). An explicit flags argument changes which keys are written (`EXTR_SKIP`, `EXTR_IF_EXISTS`) and under what names (`EXTR_PREFIX_*`), and is not modelled at all.

## Array types

### B456. Writes through a list's own keys drop `list`, while `unset()` of an element keeps it
**Impact: Low-Medium · Complexity: Medium**

```php
/** @param list<array<string, string>> $list */
function f(array $list) {
    foreach ($list as $k => $v) {
        unset($list[$k]['abc']);
    }
    $list; // should be list<array<string, string>>, is array<int, ...>

    foreach ($list as $k => $v) {
        if (rand(0, 1)) { unset($list[$k]); }
    }
    $list; // should be array<int, ...>, is list<...>
}
```

Writing to (or unsetting inside) an element the list already has keeps it a list. Removing an element does not: it leaves a gap in the keys. Both are backwards today. Writing through the keys of a `foreach` also marks the outer array non-empty, though the loop may not have run at all.

Found porting PHPStan's `Rules/Methods/data/bug-12927.php`, `Rules/Variables/data/bug-14124.php` and `Rules/Variables/data/bug-14124b.php`; the assertions are `// SKIP` in the ported copies under `tests/phpstan_data/`.

### B460. Array shape unions are merged differently from PHPStan
**Impact: Low · Complexity: Medium**

```php
/** @var mixed[][] $review */
$review = ['Review' => ['id' => 23], /* … */];
if ($cond) {
    $review['Review'] = ['id' => null, 'text' => null];
}
$review; // should be array<array<mixed>>, is array<int|string, array|array{id: null, text: null}>
```

Joining branches that wrote different shapes leaves a redundant `array|array{…}` union where the shape is already covered by `array`, and joining shapes with differing optional keys (`Rules/Comparison/data/bug-7898.php`) marks keys required that only one branch set.

A shape a sibling variant already covers is kept beside it as well: the passes of a loop that writes `['count' => $n, 'item' => $item]` into an array leave `array{count: int|float, item: mixed}` beside `array{count: mixed, item: mixed}`, and an `array{}` that `array<K, V>` covers makes an offset read on the joined array include `null`.

Found porting PHPStan's `Rules/Variables/data/bug-8113.php`, `Rules/Comparison/data/bug-7898.php`, `Rules/Methods/data/bug-5749.php` and `Rules/Arrays/data/slevomat-foreach-array-key-exists-bug.php`; the assertions are `// SKIP` in the ported copies under `tests/phpstan_data/`.

### B483. Appending a literal to a declared array widens it to its base type
**Impact: Low · Complexity: Low-Medium**

```php
/** @param non-empty-array<int> $c */
function f(array $c) {
    array_push($c, 19, 'baz', false);
    $c; // should be non-empty-array<'baz'|int|false>, is non-empty-array<int|string|false>
}
```

A keyed write outside a loop keeps the literal it stores, and so does an append onto a tracked shape, but an append (`$c[] = 'baz'`, or `array_push()`) onto an `array<K, V>` or `list<T>` still widens the value to its base type. Loop bodies should keep widening, as keyed writes do.

Found porting PHPStan's `Analyser/nsrt/array-push.php` and `array-unshift.php`; the assertions are `// SKIP` in the ported copies under `tests/phpstan_nsrt/`.

## Laravel

No outstanding items.

## Blade

No outstanding items.

## Templates

### B463. A method template with a bound is shown as its bound inside the method
**Impact: Low · Complexity: Medium**

```php
/** @template T of A&B @param iterable<T> $items */
function f($items) {
    foreach ($items as $item) {
        $item; // should be T, is A&B
    }
}
```

An unbounded method template, and a class template, keep their name inside the body (`T`); a method template with a bound is replaced by the bound when the parameter is seeded. The bound is the right thing for completion, but the value is still `T`, and returning it should keep the template (a method template bounded by a class template shows the class template's name instead).

Found porting PHPStan's `Rules/Methods/data/bug-7511.php`, `Rules/Methods/data/bug-5562.php`, `Rules/Generics/data/bug-3769.php`, `Rules/PhpDoc/data/bug-4643.php` and `Rules/Functions/data/bug-7823.php`; the assertions are `// SKIP` in the ported copies under `tests/phpstan_data/`.

### B464. Template inference from a literal argument widens it
**Impact: Low · Complexity: Medium**

```php
/** @template T of int @param T $a @return T */
function intBound(int $a) { return $a; }
intBound(1); // should be 1, is int

/** @template T @param iterable<T> $it @return iterable<array<T>> */
function chunk(iterable $it) {}
chunk([1]); // should be iterable<array<1>>, is iterable<array<int>>
chunk([]);  // should be iterable<array<never>>, is iterable<array<mixed>>
```

An unbounded template bound from a literal keeps it (`mixedBound(1)` is `1`), but a template bounded by a scalar type widens it to the bound, and a template bound through an array literal's elements widens them. An empty literal should bind `never`.

Found porting PHPStan's `Rules/Generics/data/bug-3769.php` and `Rules/Methods/data/bug-5757.php`; the assertions are `// SKIP` in the ported copies under `tests/phpstan_data/`.

### B465. A new object with unbound templates assigned to a generic property keeps the bounds
**Impact: Low-Medium · Complexity: Medium**

```php
/** @var \SplObjectStorage<\DateTimeImmutable, null> */
public $dates;
public function __construct() {
    $this->dates = new \SplObjectStorage();
    $this->dates; // should be SplObjectStorage<DateTimeImmutable, null>, is SplObjectStorage<object, mixed>
}
```

When nothing in the constructor call binds a template, the object can still become whatever the declared type of the place it is stored says. PHPStan infers those templates from the property's declared type; here they fall back to their bounds and the read-back type loses the declaration.

Found porting PHPStan's `Rules/Properties/data/bug-3777.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_data/`.

### B466. A template nested in `class-string<Foo<T>>` is not inferred
**Impact: Low · Complexity: Medium**

```php
/** @template T of OptionPresenter
 *  @param class-string<OptionDefinition<T>> $definition @return T */
function present($definition) {}
present(SimpleOptionDefinition::class); // should be SimpleOptionPresenter
                                        // (via @implements OptionDefinition<SimpleOptionPresenter>),
                                        // is class-string<SimpleOptionDefinition>
```

Binding `T` needs the named class's ancestor `OptionDefinition<…>` arguments. `class-string<T>` binds directly, but the nested case falls back to the argument's own type.

Found porting PHPStan's `Rules/Methods/data/bug-4552.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_data/`.

### B468. A conditional return type on `$param is not null` does not pick up a template bound by a callable argument
**Impact: Low · Complexity: Medium-High**

```php
/** @template T */
interface PromiseInterface {
    /** @template TFulfilled
     *  @param (callable(T): TFulfilled)|null $onFulfilled
     *  @return PromiseInterface<($onFulfilled is not null ? TFulfilled : T)> */
    public function then(callable $onFulfilled = null);
}
/** @param PromiseInterface<true> $p */
function f(PromiseInterface $p) {
    $p->then(static fn (bool $b): bool => $b); // should be PromiseInterface<bool>, is PromiseInterface<mixed>
}
```

The conditional picks its branch, but the template in that branch is bound from the closure's return type, and that binding does not reach the conditional's evaluation.

Found porting PHPStan's `Rules/Methods/data/conditional-complex-templates.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_data/`.

### B469. A conditional return type whose subject is an offset of a template is not evaluated
**Impact: Low · Complexity: Medium**

```php
/** @template T of list<string>|list<list<string>>
 *  @param T $bar @return (T[0] is string ? array{T} : T) */
function foo(array $bar): array {}
foo(['foo', 'bar']); // should be array{array{'foo', 'bar'}}, is array{0: 'foo', 1: 'bar'}
```

The subject `T[0]` is an offset access on the bound template, which has to be evaluated before the condition can be decided. Today the else branch is taken.

Found porting PHPStan's `Rules/PhpDoc/data/bug-8609-function.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_data/`.

### B470. An offset access on a type alias is not evaluated
**Impact: Low · Complexity: Medium**

```php
/** @phpstan-type Bob array{a: string, b: bool} */
class Y {
    /** @template TKey of key-of<Bob> @param TKey $key @return Bob[TKey] */
    public function x(string $key) {}
}
$y->x('b'); // should be bool, is Bob['b']
```

The offset access is printed raw rather than resolved: the alias inside it is never expanded, so the key lookup has no shape to read. `Alias[value-of<T>]` with an enum-case template argument (`Rules/PhpDoc/data/bug-11033.php`) fails the same way.

Found porting PHPStan's `Rules/PhpDoc/data/bug-13652.php` and `Rules/PhpDoc/data/bug-11033.php`; the assertions are `// SKIP` in the ported copies under `tests/phpstan_data/`.

### B480. An argument outside a method template's bound binds the template anyway
**Impact: Low · Complexity: Medium**

```php
/** @template E of Entity */
class Repository {
    /** @template F of E @param F $entity @return F */
    function store(Entity $entity): Entity {}
}
/** @extends Repository<User> */
class UserRepository extends Repository {}
$r->store(new Article()); // should be User, is Article
```

`Article` is an `Entity` but not a `User`, so it cannot be `F`. The call binds `F` to the argument's type regardless of the bound, where it should fall back to the bound it fails to satisfy.

Found porting PHPStan's `Rules/PhpDoc/data/bug-4643.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_data/`.

### B472. A template bound through a nested callable parameter is not inferred
**Impact: Low · Complexity: Medium**

```php
/** @template T @param callable(callable():T):T $closure @return T */
function bar(callable $closure) {}
/** @param callable(callable():int):string $callable */
function testBar($callable) { bar($callable); } // should be string, is mixed
```

Unifying the parameter's `callable(callable(): T): T` with the argument's signature should bind `T` from the outer return type (where the argument says `string`).

Found porting PHPStan's `Rules/Functions/data/varying-acceptor.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_data/`.

## Miscellaneous

### B482. `__PROPERTY__` inside a property hook resolves to its base type rather than its value
**Impact: Low · Complexity: Low**

```php
class User {
    public string $name {
        get {
            __PROPERTY__; // should be 'name', is string
        }
    }
}
```

`__PROPERTY__` (PHP 8.4) is known at the point it is written, the way `__FUNCTION__`/`__METHOD__` are for a method.

Found porting PHPStan's `Analyser/Fiber/data/fnsr.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_data/`.

### B475. A variable a closure captures by reference does not take on the closure's assignments
**Impact: Low · Complexity: Medium**

```php
$a = 0;
$cb = function () use (&$a): void {
    $a; // should be 0|'s', is 0
    $a = 's';
};
$a; // should be 0|'s', is 0
```

A by-reference capture shares the variable with the closure, and the closure may run any number of times before or after either read. Both sides should see the union of every value the closure assigns.

Found porting PHPStan's `Analyser/Fiber/data/fnsr.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_data/`.
