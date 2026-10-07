# PHPantom — Bug Fixes

Every bug below must be fixed at its root cause. "Detect the
symptom and suppress the diagnostic" is not an acceptable fix.
If the type resolution pipeline produces wrong data, fix the
pipeline so it produces correct data. Downstream consumers
(diagnostics, hover, completion, definition) should never need
to second-guess upstream output.

Each entry below carries an **Impact · Complexity** rating using the same
scale defined in [`docs/todo.md`](../todo.md), but a bug's row lives
**here only** — do not add or link a bug entry to `docs/todo.md`'s sprint
or backlog tables. This file is its own list, not a domain document
sprint items draw from: whenever it holds anything, that is actively
addressed, independently of sprint planning.

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

### B550. Type-check functions only narrow when written in lowercase

**Impact: Low · Complexity: Low**

PHP function names are case-insensitive, so `Is_String($x)` and
`IS_RESOURCE($this->stream)` are the same checks as their lowercase
spellings. The guard table (`type_guard_kind_from_name` in
`type_engine/types/narrowing/guards.rs`), `narrows_first_argument`, and
the class-string and member-existence extractors match the name
case-sensitively, so a mixed-case guard narrows nothing and the branch
keeps the wide type. The `is_a()` and `in_array()` extractors already
compare case-insensitively. Fold the name to lowercase once where these
helpers read it (without allocating on the common lowercase path).

### B555. Comparing an array to an array literal with `===` narrows neither branch

**Impact: Low · Complexity: Medium**

```php
/** @param list{string} $arr */
function takesStringList(array $arr): void {}

function caller(?string $val): void
{
    $arr = [$val];           // array{string|null}
    if ($arr === [null]) {
        return;              // $arr is still array{string|null} here, not array{null}
    }
    takesStringList($arr);   // reported, but $arr can only be array{string} here
}
```

`literal_comparand_type`
(`src/type_engine/variable/forward_walk/cond_narrowing/predicates.rs`)
recognises only the empty array among array literals, so
`apply_literal_identity_narrowing` (`cond_narrowing/emptiness.rs`) never
sees `[null]` as a value to pin or strip, and `$arr` keeps its type in both
branches. Guarding on the element instead (`$arr[0] === null`) narrows
correctly. The equal branch should narrow the subject to the literal's
shape (`array{null}`). The unequal branch can subtract the literal from a
shape with the same keys when every other entry is already pinned to the
literal's value, which a one-entry shape always is.

The guarded call became a false positive in 0.11.0, which started
reporting the unguarded one (`array{string|null}` passed to
`list{string}`). PHPStan and Psalm report it too. mago narrows.

Found running the php-typing-conformance suite
(`regressions_array_element_null_subtraction.php`).

### B559. A `match (true)` arm with several conditions is narrowed as if all of them held

**Impact: Medium · Complexity: Low**

```php
function f(Cat|Dog|null $p): void {
    match (true) {
        // Runs when *either* check holds, so `$p` is `Cat|Dog` here,
        // but no mismatch is reported.
        $p instanceof Cat, $p instanceof Dog => takesDog($p),
        default => null,
    };
}
```

The arm body runs when any one of its conditions is `true`, but both the
diagnostic snapshot walker (`record_match_ternary_snapshots`) and the
completion/hover walker (`apply_cursor_ternary_narrowing`) apply each
condition's truthy narrowing to the same scope in turn, which is the
narrowing of `a && b`. Each condition should narrow its own copy of the
scope and the copies should be joined, as the `||` pass does.

### B560. An `&&` inside a `match (true)` arm condition does not narrow its later operands

**Impact: Medium · Complexity: Low**

```php
function f(?string $s): void {
    match (true) {
        // `strlen($s)` reports `?string`.
        is_string($s) && strlen($s) > 1 => null,
        default => null,
    };
}
```

The same condition narrows its right operand in an `if`, an assignment,
or a ternary, and an `&&` chain in an arm *body* narrows too. Only the arm
conditions of a `match (true)` are missing from the short-circuit snapshot
recording.

### B561. A `match (true)` passed straight into a call ignores its arm narrowing

**Impact: Medium · Complexity: Medium**

```php
function f(Cat|Dog $p): void {
    // Reports `Dog|Cat`; assigned to a variable first, the value is `Dog`.
    takesDog(match (true) { $p instanceof Cat => new Dog(), default => $p });
}
```

The `match` value's arm narrowing lives in the `Expression::Match` case of
`resolve_rhs_expression` (`rhs_resolution/mod.rs`), and none of it reaches
an argument, not even the `instanceof` extractor that works without a
scope. The same ternary passed as an argument does narrow, so the argument
path resolves a `match` through some other route than the ternary's; find
it and route it through the shared one.

## Arithmetic

No outstanding items.

## Symbol resolution

No outstanding items.

## Array types

### B565. A plain array counts as a subtype of an unsealed array shape

**Impact: Low · Complexity: Low-Medium**

```php
/** @param array{foo: int, ...} $shape */
function takeOpen(array $shape): void {}
```

`array<string, int>` is a subtype of `array{foo: int, ...}` as far as
`is_subtype_of_typed` is concerned, and `non-empty-array<string, int>` is
one for `PhpType::is_subtype_of` too, though nothing says either array
holds `foo`. The same goes for `list<int>` and `non-empty-list<int>`
against `list{int, ...}`. The shape-to-shape rules read an unsealed shape
through `shape_parts()`, but a subtype that is not itself a shape reaches
an unsealed supertype as the `non-empty-array<array-key, mixed>` it widens
to, which any array-like generic fits. The class-aware generic covariance
rule in `is_subtype_of_typed` does not even check the `non-empty-` promise,
which is why a bare `array<string, int>` passes there. A sealed supertype
has no such problem: `non-empty-array<string, int>` is not a subtype of
`array{foo: int}`. Parameter seeding reads the answer as a proven
narrowing of the declared type.

**Fix:** Settle an array-like generic against an unsealed supertype as an
unsealed shape with no entries of its own, `array{...<K, V>}` (or
`list{...<V>}` for a list), through `shape_is_subshape`: an entry the
supertype requires is then missing, and an optional one has to fit the
tail. Do it in `PhpType::is_subtype_of` and, ahead of the generic-array
rules, in `is_subtype_of_typed`. The argument diagnostic answers a typed
array handed to an unsealed parameter through its own rules, not through
these two functions; keep it that way, so that an array that merely might
hold the entries stays unreported.

### B566. A `list` parameter rejects a docblock shape keyed by class constants

**Impact: Low · Complexity: Low-Medium**

```php
class Slots { const NAME = 0; const AGE = 1; }

/** @return array{Slots::NAME: string, Slots::AGE: int} */
function row(): array { return ['Ann', 30]; }

/** @param list<string|int> $values */
function takeList(array $values): void {}

takeList(row()); // reported, though the keys are `0` and `1`
```

`shape_fits_array` (`src/diagnostics/type_errors/compatibility.rs`) holds
the keys of a shape to the integers a list demands by their spelling, and
a key spelled `Slots::NAME` is not one, so the shape is rejected whatever
the constant evaluates to. An array literal does not have the problem,
because its keys are evaluated: `[Slots::NAME => 'Ann']` is typed
`array<0, 'Ann'>`. A docblock keeps the spelling, and `shape_key_type` can
only call such a key an `array-key`.

**Fix:** Evaluate the constant. When the class is loadable and the constant
holds an integer or string literal, read the key as that value in
`shape_fits_array`, and in the structural list check
(`shape_keys_are_sequential`), which reads the same spelling as a string
key. A constant that cannot be evaluated stays an `array-key`, which does
not contradict a list.

## Laravel

No outstanding items.

## Blade

### B567. The Laravel example's `analyze` run reports a fourth error

**Impact: Low · Complexity: Low**

`phpantom_lsp analyze --project-root examples/laravel` is documented in
`docs/CONTRIBUTING.md` to report exactly the three deliberate mistakes in
`app/Demo.php`. It also reports `Unused variable '$rowLabel'` at
`resources/views/admin/users/index.blade.php:36`. The template assigns
`$rowLabel` in `@php` and reads it only in a `:data-label` binding on a
plain `<tr>`, which Blade no longer analyses as PHP now that only `<x-…>`
component tags evaluate bound attributes, so the variable is unused as far
as the engine can tell. The comment above the line still says the
expression is real PHP.

**Fix:** Make the demo say what it means: put the bound attributes on a
component tag the example project defines, or read `$rowLabel` where Blade
does evaluate it. Then the run is back to three errors.

## Templates

No outstanding items.

## Miscellaneous

### B549. `@throws` and namespaced-function completions plan their import against the whole file

**Impact: Low · Complexity: Low-Medium**

The `@throws` smart items (`src/completion/phpdoc/mod.rs`), the `@throws`
imports of docblock generation (`build_throws_import_edits` in
`src/completion/phpdoc/generation/mod.rs`) and `build_function_completions`
(`src/completion/context/function_completion.rs`) build their `use` edit
from `analyze_use_block(content)`, the whole file. In a file with several
`namespace` blocks the import can land in another block, which every other
import edit stopped doing this cycle; in a Blade template it lands in the
virtual prologue, so the completion carrying it is dropped. Plan them
through `Backend::use_block_for` with the block the cursor is in, as class
completion does.

### B554. `->value` and `->name` on an enum read as `string`, not as its cases' values

**Impact: Medium · Complexity: Low-Medium**

```php
enum Suit: string { case Hearts = 'hearts'; case Spades = 'spades'; }

/** @param value-of<Suit> $value */
function take(string $value): void {}

take($suit->value);  // reported: expects 'hearts'|'spades', got string

final class Card
{
    public function __construct(private Suit $suit) {}

    /** @return value-of<Suit> */
    public function suitValue(): string
    {
        return $this->suit->value; // reported: string is incompatible with 'hearts'|'spades'
    }
}
```

The inheritance merge (`src/inheritance/mod.rs`, where it refines a backed
enum's `value` property) narrows `BackedEnum::$value` from `int|string` to
the enum's backing type and stops there, and `UnitEnum::$name` stays
`string`. A named case already reads as its own literal
(`Suit::Hearts->value` is `'hearts'`), but a value typed as the enum reads
as the bare scalar. This became a false positive in 0.11.0, when
`value-of<…>` over an enum started evaluating to the cases' values instead
of staying unevaluated (which accepted anything): every `value-of<Enum>`
parameter or return fed an enum's `->value` is now reported. So is a
declared literal union (`@return 'hearts'|'spades'`), and a `@template T of
Suit` function returning `$case->value` as `value-of<T>`.

**Fix:** Refine `value` to the union of every case's backing value, and
`name` to the union of the case names, as PHPStan and Psalm do. When a
case's value cannot be read (a constant expression the folder does not
handle), keep the backing type.

Found running the php-typing-conformance suite
(`phpdoc_advanced_fallback_value_of_template_enum.php`).

### B556. Moving a class out of a braced global `namespace { }` block writes an unbracketed `namespace`

**Impact: Low · Complexity: Low-Medium**

```php
<?php
namespace { class Foo {} }
```

Moving `Foo` to `C\Foo` inserts `namespace C;` above the block, and PHP
refuses a file that mixes bracketed and unbracketed `namespace`
declarations. A braced global block has no name for
`namespace_declaration_edits` (`src/rename/class/mod.rs`) to rewrite, so the
move takes the path for a file that had no `namespace` at all. Write the new
name after the block's `namespace` keyword instead, and place the imports the
former global siblings now need in that block.

### B558. Removing two unused members at the end of a group import breaks the statement

**Impact: Medium · Complexity: Low-Medium**

```php
use App\Models\{User, Post, Comment};   // only User is used
```

"Remove all unused imports" and `phpantom_lsp fix` turn this into
`use App\Models\{User, `, dropping the closing `};`. Each member is removed
on its own by `extend_range_for_group_member`
(`src/code_actions/remove_unused_import.rs`): a member takes the comma after
it, or the one before it when it is the last, so `Post` takes `Post, ` and
`Comment` takes `, Comment`. The two edits overlap, and `apply_text_edits`
applies the second against text the first already changed. A member has to
choose its comma knowing the rest of the batch: the one after it while every
member before it is removed too, the one before it otherwise. That way no
two removals share a comma. The removal of a template's `@use` group
members (`group_member_removal`, in the same file) already chooses this way.

### B562. An import written into a `namespace` block that sits on one line lands after the block

**Impact: Low · Complexity: Medium**

```php
<?php
namespace B { class Foo { public function f(): Helper {} } }
```

With `B\Helper` declared elsewhere, moving `B\Foo` to `C\Foo` writes
`use B\Helper;` on the line below the block, outside every `namespace`, and
PHP refuses the file. `analyze_use_block_in` (`src/completion/use_edit.rs`)
puts the first import of a block that has none on the line after its
`namespace` line, which is past the block when the block closes on that
line, and every import planned through it shares the placement. The import
belongs just after the `{` or `;` of the declaration, which `UseBlockInfo`
cannot express: its positions are whole lines.
