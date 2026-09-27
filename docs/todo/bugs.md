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

### B512. A call whose argument is `*NEVER*` still has its declared return type
**Impact: Low · Complexity: Low-Medium**

```php
$this->foo = 'x';
if (strlen($this->foo) > 0) {
    return;
}
$this->foo;         // *NEVER* (correct)
strlen($this->foo); // should be *NEVER*, is int<0, max>
```

Evaluating the argument never completes, so neither does the call. The call resolvers read the callee's return type without looking at whether an argument could have been produced at all.

Found porting PHPStan's `nsrt/bug-5129.php`; the assertion is `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

## Narrowing

No outstanding items.

## Arithmetic

No outstanding items.

## Symbol resolution

No outstanding items.

## Array types

### B511. A union of shapes is not folded when one alternative covers another
**Impact: Low · Complexity: Medium**

```php
/** @param array{mixed}|array{0: mixed, 1?: string|null} $row */
function f(array $row) {
    $row; // should be array{0: mixed, 1?: string|null}, is array{mixed}|array{0: mixed, 1?: string|null}
}
```

`array{mixed}` is one of the values `array{0: mixed, 1?: string|null}` describes, so the union is the larger shape alone. The join after a branch has the same gap from the other side: `join_shapes` refuses shapes with positional entries, so `array{mixed}` and `array{0: mixed, 1: string|null}` from the two sides of `if (count($row) === 2)` stay two alternatives instead of folding back into `array{0: mixed, 1?: string|null}`.

Found porting PHPStan's `nsrt/list-count.php`; the assertions are `// SKIP` in the ported copy under `tests/phpstan_nsrt/`.

## Laravel

No outstanding items.

## Blade

No outstanding items.

## Templates

No outstanding items.

## Miscellaneous

No outstanding items.
