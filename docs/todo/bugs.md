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

No outstanding items.

## Array types

### B514. `join_shapes` refuses a shape with a positional entry even when the other side anchors it

**Impact: Low · Complexity: Medium**

```php
/**
 * @param array{mixed}|array{0: mixed, 1?: string|null} $row
 */
function f($row) {
    if (count($row) === 2) {
        // array{mixed, string|null}
    } else {
        // array{mixed}
    }
    $row; // should re-fold to array{0: mixed, 1?: string|null}, PHPantom keeps two alternatives
}
```

The union-folding half of this gap (`array{mixed}|array{0: mixed, 1?: string|null}` collapsing to the wider shape as written, with no branching involved) is fixed: `PhpType::union` now drops a shape member another member already covers exactly (`absorb_subsumed_shapes` in `php_type/normalize.rs`).

The branch-join half is not: `join_shape_entries` (`php_type/mod.rs`) refuses to pair up two shapes at all when either side has a positional (keyless) entry, on the theory that two independently-written positional literals describe unrelated arrays and pairing their slots by position would invent a row neither holds. That is the right call when *both* sides are bare positional shapes (`array{int, string}` and `array{float}` must stay separate), but it is too strict when the *other* side spells the position out with an explicit key (`array{mixed}` against `array{0: mixed, 1?: string|null}`): the explicit key is exactly the anchor that makes the pairing safe, but `join_shape_entries` blocks it before ever looking at the other side.

A first attempt loosened the block to allow this ("anchored" case) but it over-fired: `array{string}|array{0: int, 1?: string|null}` (a genuine two-branch tagged union, not a single positional shape) narrowed by `count($row) === 2` produces `array{string}` (unchanged) beside `array{0: int}` (narrowed, keeping its *explicit* key from the original docblock, not a bare positional one), and the loosened check joined those into one shape (`array{0: int|string}`) where PHPStan and the existing test suite (`testOptionalKeysInListsOfTaggedUnion` in `tests/phpstan_nsrt/list-count.php`) expect them to stay two alternatives. So "the other side has an explicit key at that position" is not by itself sufficient to prove the pairing is safe — the fix needs something more, e.g. only pairing when the position is the *only* thing distinguishing the two shapes, not when the two shapes are alternatives of a union that a completely separate code path produced by narrowing.

Found porting PHPStan's `nsrt/list-count.php`.

## Laravel

No outstanding items.

## Blade

No outstanding items.

## Templates

No outstanding items.

## Miscellaneous

No outstanding items.
