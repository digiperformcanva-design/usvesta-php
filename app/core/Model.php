<?php
/**
 * Small Django-like ORM: models with typed fields, forward FKs, reverse "<model>_set" managers,
 * and a QuerySet supporting Django-style lookups (field__icontains, rel__field, __in, __isnull ...).
 */

final class FieldFile implements JsonSerializable
{
    public function __construct(public string $name) {}
    public function url(): string { return $this->name === '' ? '' : '/media/' . ltrim(str_replace('\\', '/', $this->name), '/'); }
    public function path(): string { return BASE_PATH . '/media/' . ltrim($this->name, '/'); }
    public function tplGet(string $k): mixed
    {
        return match ($k) {
            'url' => $this->name === '' ? Undefined::get() : $this->url(),
            'name' => $this->name,
            'path' => $this->path(),
            'size' => is_file($this->path()) ? filesize($this->path()) : 0,
            default => Undefined::get(),
        };
    }
    public function __get($k) { $v = $this->tplGet($k); return $v instanceof Undefined ? null : $v; }
    public function __toString(): string { return $this->name; }
    public function __bool(): bool { return $this->name !== ''; }
    public function jsonSerialize(): mixed { return $this->name; }
}

abstract class Model implements JsonSerializable
{
    /** field => type. Types: auto, int, str, text, bool, float, decimal:N, datetime, date, file, json, uuid, fk:Class, csv */
    protected static string $table = '';
    protected static array $fields = [];
    protected static string $pk = 'id';
    protected static array $defaults = [];

    public array $attrs = [];
    public array $extra = [];
    private array $relCache = [];
    public bool $exists = false;

    public function __construct(array $attrs = [])
    {
        foreach (static::$defaults as $k => $v) $this->attrs[$k] = is_callable($v) ? $v() : $v;
        foreach ($attrs as $k => $v) $this->__set($k, $v);
    }

    /* ------------------- metadata */
    public static function table(): string { return static::$table; }
    public static function pkName(): string { return static::$pk; }
    public static function fields(): array { return static::$fields; }
    protected static function f(): array { return static::fields(); }
    public function pk(): mixed { return $this->attrs[static::$pk] ?? null; }

    public static function column(string $field): ?string
    {
        $t = static::f()[$field] ?? null;
        if ($t === null) {
            if (str_ends_with($field, '_id') && array_key_exists(substr($field, 0, -3), static::f())) return $field;
            return null;
        }
        return str_starts_with($t, 'fk:') ? $field . '_id' : $field;
    }

    public static function fkTarget(string $field): ?string
    {
        $t = static::f()[$field] ?? '';
        return str_starts_with($t, 'fk:') ? substr($t, 3) : null;
    }

    /* ------------------- query API */
    public static function objects(): QuerySet { return new QuerySet(static::class); }
    public static function all(): QuerySet { return static::objects(); }
    public static function filter(array $c = []): QuerySet { return static::objects()->filter($c); }
    public static function exclude(array $c): QuerySet { return static::objects()->exclude($c); }
    public static function find(mixed $id): ?static
    {
        if ($id === null || $id === '') return null;
        return static::objects()->filter([static::$pk => $id])->first();
    }
    public static function first_or_null(array $c): ?static { return static::filter($c)->first(); }

    public static function create(array $data): static
    {
        $m = new static($data);
        $m->save();
        return $m;
    }

    public static function get_or_create(array $lookup, array $defaults = []): array
    {
        $m = static::filter($lookup)->first();
        if ($m) return [$m, false];
        return [static::create(array_merge($lookup, $defaults)), true];
    }

    public static function hydrate(array $row): static
    {
        $m = new static();
        $m->attrs = $row;
        $m->exists = true;
        return $m;
    }

    /* ------------------- persistence */
    protected function beforeSave(bool $creating): void {}
    protected function afterSave(bool $created): void {}

    public function save(): static
    {
        $creating = !$this->exists;
        $this->beforeSave($creating);
        $cols = [];
        foreach (static::f() as $f => $type) {
            if ($type === 'auto') continue;
            $col = static::column($f);
            if (array_key_exists($col, $this->attrs)) $cols[$col] = $this->dbValue($type, $this->attrs[$col]);
        }
        if ($creating) {
            if (static::f()[static::$pk] !== 'auto' && array_key_exists(static::$pk, $this->attrs)) $cols[static::$pk] = $this->attrs[static::$pk];
            $names = implode(',', array_map([DB::class, 'q'], array_keys($cols)));
            $ph = implode(',', array_fill(0, count($cols), '?'));
            DB::query('INSERT INTO ' . DB::q(static::$table) . " ($names) VALUES ($ph)", array_values($cols));
            if ((static::f()[static::$pk] ?? '') === 'auto') $this->attrs[static::$pk] = (int)DB::lastId();
            $this->exists = true;
        } else {
            $set = implode(',', array_map(fn($c) => DB::q($c) . '=?', array_keys($cols)));
            if ($set !== '') DB::query('UPDATE ' . DB::q(static::$table) . " SET $set WHERE " . DB::q(static::$pk) . '=?', [...array_values($cols), $this->pk()]);
        }
        $this->afterSave($creating);
        return $this;
    }

    public function delete(): void
    {
        static::objects()->filter([static::$pk => $this->pk()])->delete();
        $this->exists = false;
    }

    /** Fields to apply for "UPDATE only these" without hooks. */
    public static function updateWhere(array $filter, array $values): int { return static::filter($filter)->update($values); }

    private function dbValue(string $type, mixed $v): mixed
    {
        if ($v === null) return null;
        if ($v instanceof Model) return $v->pk();
        if ($v instanceof FieldFile) return $v->name;
        if ($v instanceof SafeString) $v = $v->value;
        if ($type === 'bool') return $v ? 1 : 0;
        if ($type === 'json') return is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($type === 'datetime') {
            if ($v instanceof DateTimeInterface) return (new DateTimeImmutable('@' . $v->getTimestamp()))->format('Y-m-d H:i:s');
            return (string)$v;
        }
        if ($type === 'csv' && is_array($v)) return implode(',', $v);
        if (str_starts_with($type, 'decimal')) return is_numeric($v) ? (string)$v : $v;
        return $v;
    }

    /* ------------------- attribute access */
    public function __set(string $k, mixed $v): void
    {
        $t = static::f()[$k] ?? null;
        if ($t !== null && str_starts_with($t, 'fk:')) {
            $this->attrs[$k . '_id'] = $v instanceof Model ? $v->pk() : $v;
            $this->relCache[$k] = $v instanceof Model ? $v : null;
            if (!($v instanceof Model)) unset($this->relCache[$k]);
            return;
        }
        if ($t !== null || str_ends_with($k, '_id') && array_key_exists(substr($k, 0, -3), static::f())) {
            if (str_ends_with($k, '_id')) unset($this->relCache[substr($k, 0, -3)]);
            $this->attrs[$k] = $v;
            return;
        }
        $this->extra[$k] = $v;
    }

    public function __isset(string $k): bool { return !($this->tplGet($k) instanceof Undefined); }

    public function __get(string $k): mixed
    {
        $v = $this->tplGet($k);
        return $v instanceof Undefined ? null : $v;
    }

    public function raw(string $col): mixed { return $this->attrs[$col] ?? null; }

    public function tplGet(string $k): mixed
    {
        if (array_key_exists($k, $this->extra)) return $this->extra[$k];
        $t = static::f()[$k] ?? null;
        if ($t !== null) {
            if (str_starts_with($t, 'fk:')) {
                if (array_key_exists($k, $this->relCache)) return $this->relCache[$k];
                $id = $this->attrs[$k . '_id'] ?? null;
                $cls = substr($t, 3);
                return $this->relCache[$k] = ($id === null || $id === '') ? null : $cls::find($id);
            }
            $v = $this->attrs[$k] ?? null;
            return self::cast($t, $v);
        }
        if (str_ends_with($k, '_id') && array_key_exists(substr($k, 0, -3), static::f())) return $this->attrs[$k] ?? null;
        if ($k === 'pk') return $this->pk();
        if (str_ends_with($k, '_set')) return $this->reverse(substr($k, 0, -4));
        return Undefined::get();
    }

    public static function cast(string $t, mixed $v): mixed
    {
        switch (true) {
            case $t === 'bool': return $v === null ? null : (bool)(int)$v;
            case $t === 'int' || $t === 'auto': return $v === null || $v === '' ? ($v === '' ? '' : null) : (is_numeric($v) ? (int)$v : $v);
            case $t === 'float': return $v === null ? null : (float)$v;
            case str_starts_with($t, 'decimal'):
                if ($v === null || $v === '') return $v;
                $places = (int)(explode(':', $t)[1] ?? 2);
                return is_numeric($v) ? new Decimal(number_format((float)$v, $places, '.', '')) : $v;
            case $t === 'file': return new FieldFile((string)($v ?? ''));
            case $t === 'json':
                if ($v === null || $v === '') return null;
                if (is_array($v)) return $v;
                $d = json_decode((string)$v, true);
                return json_last_error() === JSON_ERROR_NONE ? $d : $v;
            case $t === 'datetime': case $t === 'date':
                if ($v === null || $v === '') return null;
                if ($v instanceof DateTimeInterface) return to_local($v);
                try { return to_local(new DateTimeImmutable((string)$v, new DateTimeZone('UTC'))); } catch (Throwable) { return null; }
            case $t === 'csv':
                if ($v === null || $v === '') return [];
                if (is_array($v)) return $v;
                return array_values(array_filter(array_map('trim', explode(',', (string)$v)), fn($x) => $x !== ''));
        }
        return $v;
    }

    /** Reverse FK manager, Django naming: <lowercase model name>_set */
    public function reverse(string $modelLower): QuerySet|array
    {
        $cls = Registry::byLower($modelLower);
        if (!$cls) return [];
        foreach ($cls::fields() as $f => $type) {
            if ($type === 'fk:' . static::class || strcasecmp($type, 'fk:' . static::class) === 0) {
                return $cls::filter([$f . '_id' => $this->pk()]);
            }
        }
        return [];
    }

    public function toArray(): array
    {
        $o = [];
        foreach (static::f() as $f => $t) {
            $v = $this->tplGet($f);
            if (str_starts_with($t, 'fk:')) { $o[$f . '_id'] = $this->attrs[$f . '_id'] ?? null; continue; }
            $o[$f] = to_plain($v);
        }
        return $o;
    }

    public function jsonSerialize(): mixed { return $this->toArray(); }

    public function __toString(): string { return static::class . ' object (' . $this->pk() . ')'; }
}

/** Decimal value: prints like Python's Decimal ("1199.00"), compares numerically. */
final class Decimal implements JsonSerializable
{
    public function __construct(public string $v) {}
    public function __toString(): string { return $this->v; }
    public function float(): float { return (float)$this->v; }
    public function __bool(): bool { return (float)$this->v != 0; }
    public function jsonSerialize(): mixed { return $this->v; }
}

final class Registry
{
    private static array $models = [];
    public static function register(string ...$classes): void { foreach ($classes as $c) self::$models[strtolower($c)] = $c; }
    public static function byLower(string $n): ?string { return self::$models[strtolower($n)] ?? null; }
    public static function all(): array { return self::$models; }
}

/* ======================================================================= QuerySet */

final class QuerySet implements IteratorAggregate, Countable, ArrayAccess, JsonSerializable
{
    private array $where = [];   // [sql, params]
    private array $order = [];
    private ?int $limit = null;
    private int $offset = 0;
    private ?array $cache = null;
    private bool $none = false;

    public function __construct(public string $model) {}

    public function __clone() { $this->cache = null; }

    public function all(): QuerySet { return clone $this; }
    public function none(): QuerySet { $q = clone $this; $q->none = true; return $q; }

    public function filter(array $conds): QuerySet
    {
        $q = clone $this;
        foreach ($conds as $k => $v) $q->where[] = self::compile($this->model, $k, $v);
        return $q;
    }

    public function exclude(array $conds): QuerySet
    {
        $q = clone $this;
        $parts = []; $params = [];
        foreach ($conds as $k => $v) { [$s, $p] = self::compile($this->model, $k, $v); $parts[] = $s; array_push($params, ...$p); }
        if ($parts) {
            // Django exclude(): NOT (a AND b); NULL-safe: rows where condition is NULL are kept
            $q->where[] = ['NOT COALESCE((' . implode(' AND ', $parts) . '), 0)', $params];
        }
        return $q;
    }

    /** Raw SQL condition (columns of this model's table). */
    public function whereRaw(string $sql, array $params = []): QuerySet { $q = clone $this; $q->where[] = [$sql, $params]; return $q; }

    /** OR of several condition-arrays: anyOf([['a'=>1], ['b__icontains'=>'x']]) */
    public function anyOf(array $groups): QuerySet
    {
        $q = clone $this;
        $ors = []; $params = [];
        foreach ($groups as $g) {
            $ands = [];
            foreach ($g as $k => $v) { [$s, $p] = self::compile($this->model, $k, $v); $ands[] = $s; array_push($params, ...$p); }
            $ors[] = '(' . implode(' AND ', $ands ?: ['1=1']) . ')';
        }
        $q->where[] = ['(' . implode(' OR ', $ors ?: ['1=0']) . ')', $params];
        return $q;
    }

    public function order_by(string ...$fields): QuerySet
    {
        $q = clone $this;
        $q->order = [];
        $m = $this->model;
        foreach ($fields as $f) {
            if ($f === '?') { $q->order[] = DB::$driver === 'sqlite' ? 'RANDOM()' : 'RAND()'; continue; }
            $desc = str_starts_with($f, '-');
            $name = ltrim($f, '-');
            if ($name === 'pk') $name = $m::pkName();
            $col = $m::column($name) ?? $name;
            $q->order[] = DB::q($col) . ($desc ? ' DESC' : ' ASC');
        }
        return $q;
    }

    public function slice(int $offset, ?int $length = null): QuerySet
    {
        $q = clone $this;
        $q->offset = $this->offset + $offset;
        $q->limit = $length;
        return $q;
    }
    public function limit(int $n): QuerySet { return $this->slice(0, $n); }

    private function whereSql(): array
    {
        if ($this->none) return [' WHERE 1=0', []];
        if (!$this->where) return ['', []];
        $s = []; $p = [];
        foreach ($this->where as [$sql, $params]) { $s[] = "($sql)"; array_push($p, ...$params); }
        return [' WHERE ' . implode(' AND ', $s), $p];
    }

    public function toSql(string $select = '*'): array
    {
        $m = $this->model;
        [$w, $p] = $this->whereSql();
        $sql = "SELECT $select FROM " . DB::q($m::table()) . $w;
        if ($this->order) $sql .= ' ORDER BY ' . implode(', ', $this->order);
        if ($this->limit !== null) $sql .= ' LIMIT ' . (int)$this->limit . ' OFFSET ' . (int)$this->offset;
        elseif ($this->offset) $sql .= ' LIMIT 18446744073709551615 OFFSET ' . (int)$this->offset;
        if (DB::$driver === 'sqlite') $sql = str_replace('LIMIT 18446744073709551615', 'LIMIT -1', $sql);
        return [$sql, $p];
    }

    /** @return Model[] */
    public function get(): array
    {
        if ($this->cache !== null) return $this->cache;
        [$sql, $p] = $this->toSql();
        $m = $this->model;
        return $this->cache = array_map(fn($r) => $m::hydrate($r), DB::all($sql, $p));
    }

    public function first(): ?Model
    {
        if ($this->cache !== null) return $this->cache[0] ?? null;
        $q = clone $this;
        if (!$q->order) $q = $q->order_by($this->model::pkName());
        $q->limit = 1;
        return $q->get()[0] ?? null;
    }

    public function last(): ?Model
    {
        $items = $this->get();
        return $items ? end($items) : null;
    }

    public function count(): int
    {
        if ($this->cache !== null) return count($this->cache);
        if ($this->limit !== null || $this->offset) return count($this->get());
        [$w, $p] = $this->whereSql();
        return (int)DB::scalar('SELECT COUNT(*) FROM ' . DB::q($this->model::table()) . $w, $p);
    }

    public function exists(): bool { return $this->count() > 0; }
    public function __bool(): bool { return $this->exists(); }

    public function delete(): int
    {
        [$w, $p] = $this->whereSql();
        $m = $this->model;
        if (method_exists($m, 'onDelete')) {
            foreach ($this->get() as $obj) $m::onDelete($obj);
        }
        return DB::query('DELETE FROM ' . DB::q($m::table()) . $w, $p)->rowCount();
    }

    public function update(array $values): int
    {
        [$w, $p] = $this->whereSql();
        $m = $this->model;
        $set = []; $vals = [];
        foreach ($values as $k => $v) {
            $set[] = DB::q($m::column($k) ?? $k) . '=?';
            $vals[] = $v instanceof Model ? $v->pk() : (is_bool($v) ? (int)$v : ($v instanceof DateTimeInterface ? (new DateTimeImmutable('@' . $v->getTimestamp()))->format('Y-m-d H:i:s') : $v));
        }
        return DB::query('UPDATE ' . DB::q($m::table()) . ' SET ' . implode(',', $set) . $w, [...$vals, ...$p])->rowCount();
    }

    public function values_list(string $field, bool $flat = true): array
    {
        $m = $this->model;
        $col = $m::column($field) ?? $field;
        [$sql, $p] = $this->toSql(DB::q($col));
        return array_column(DB::all($sql, $p), $col);
    }

    public function aggregate(string $fn, string $field): mixed
    {
        $m = $this->model;
        $col = DB::q($m::column($field) ?? $field);
        [$w, $p] = $this->whereSql();
        $expr = match (strtolower($fn)) {
            'sum' => "SUM($col)", 'avg' => "AVG($col)", 'min' => "MIN($col)", 'max' => "MAX($col)", 'count' => "COUNT($col)",
            'minf' => 'MIN(CAST(' . $col . ' AS DECIMAL(14,2)))', 'maxf' => 'MAX(CAST(' . $col . ' AS DECIMAL(14,2)))',
        };
        return DB::scalar("SELECT $expr FROM " . DB::q($m::table()) . $w, $p);
    }

    /* ---------------- interfaces */
    public function getIterator(): ArrayIterator { return new ArrayIterator($this->get()); }
    public function offsetExists(mixed $o): bool { return isset($this->get()[$o]); }
    public function offsetGet(mixed $o): mixed { return $this->get()[$o] ?? null; }
    public function offsetSet(mixed $o, mixed $v): void {}
    public function offsetUnset(mixed $o): void {}
    public function jsonSerialize(): mixed { return $this->get(); }
    public function tplGet(string $k): mixed
    {
        if (ctype_digit($k)) return $this->get()[(int)$k] ?? Undefined::get();
        return Undefined::get();
    }

    /* ---------------- lookup compiler */
    private const LOOKUPS = ['exact', 'iexact', 'contains', 'icontains', 'in', 'isnull', 'gt', 'gte', 'lt', 'lte', 'startswith', 'istartswith', 'endswith', 'iendswith', 'year', 'month', 'day'];

    public static function compile(string $model, string $key, mixed $value): array
    {
        $parts = explode('__', $key);
        $lookup = 'exact';
        if (count($parts) > 1 && in_array(end($parts), self::LOOKUPS, true)) $lookup = array_pop($parts);
        return self::compilePath($model, $parts, $lookup, $value);
    }

    private static function compilePath(string $model, array $parts, string $lookup, mixed $value): array
    {
        $field = array_shift($parts);
        if ($field === 'pk') $field = $model::pkName();
        $target = $model::fkTarget($field);

        if ($parts && $target) {
            // traverse relation via subquery
            [$sub, $p] = self::compilePath($target, $parts, $lookup, $value);
            $col = DB::q($field . '_id');
            return ["$col IN (SELECT " . DB::q($target::pkName()) . ' FROM ' . DB::q($target::table()) . " WHERE $sub)", $p];
        }
        if ($parts && !$target) {
            // reverse relation: e.g. product_sub_category -> product (Product has fk:Product_sub_category)
            $rev = Registry::byLower($field);
            if ($rev) {
                foreach ($rev::fields() as $f => $t) {
                    if (strcasecmp($t, 'fk:' . $model) === 0) {
                        [$sub, $p] = self::compilePath($rev, $parts, $lookup, $value);
                        return [DB::q($model::pkName()) . ' IN (SELECT ' . DB::q($f . '_id') . ' FROM ' . DB::q($rev::table()) . " WHERE $sub)", $p];
                    }
                }
            }
            throw new RuntimeException("Unknown relation $field on $model");
        }

        $col = $model::column($field) ?? $field;
        if ($target && $value instanceof Model) $value = $value->pk();
        if ($value instanceof Model) $value = $value->pk();
        if ($value instanceof SafeString || $value instanceof Decimal) $value = (string)$value;
        if (is_bool($value) && $lookup !== 'isnull') $value = (int)$value;
        $c = DB::q($col);
        $ci = DB::$driver === 'mysql' ? $c : $c; // collation handles case-insensitivity in MySQL; SQLite LIKE is CI for ASCII
        switch ($lookup) {
            case 'exact':
                if ($value === null) return ["$c IS NULL", []];
                return ["$c = ?", [$value]];
            case 'iexact': return ["LOWER($c) = LOWER(?)", [$value]];
            case 'contains': case 'icontains': return ["$ci LIKE ?", ['%' . self::likeEsc((string)$value) . '%']];
            case 'startswith': case 'istartswith': return ["$ci LIKE ?", [self::likeEsc((string)$value) . '%']];
            case 'endswith': case 'iendswith': return ["$ci LIKE ?", ['%' . self::likeEsc((string)$value)]];
            case 'in':
                $vals = $value instanceof QuerySet ? $value->values_list($value->model::pkName()) : array_values(is_array($value) ? $value : iterator_to_array($value));
                $vals = array_map(fn($x) => $x instanceof Model ? $x->pk() : $x, $vals);
                if (!$vals) return ['1=0', []];
                return ["$c IN (" . implode(',', array_fill(0, count($vals), '?')) . ')', $vals];
            case 'isnull': return [$value ? "$c IS NULL" : "$c IS NOT NULL", []];
            case 'gt': return ["$c > ?", [$value]];
            case 'gte': return ["$c >= ?", [$value]];
            case 'lt': return ["$c < ?", [$value]];
            case 'lte': return ["$c <= ?", [$value]];
            case 'year':
                return ["$c >= ? AND $c < ?", [sprintf('%04d-01-01 00:00:00', $value), sprintf('%04d-01-01 00:00:00', $value + 1)]];
        }
        throw new RuntimeException("Unsupported lookup $lookup");
    }

    private static function likeEsc(string $s): string { return $s; }
}

/* ======================================================================= Paginator */

final class Paginator
{
    public int $count;
    public int $num_pages;

    public function __construct(public QuerySet|array $items, public int $per_page)
    {
        $this->count = is_array($items) ? count($items) : $items->count();
        $this->num_pages = max(1, (int)ceil($this->count / max(1, $per_page)));
    }

    public function get_page(mixed $n): Page
    {
        $n = is_numeric($n) ? (int)$n : 1;
        if ($n < 1) $n = 1;
        if ($n > $this->num_pages) $n = $this->num_pages;
        $off = ($n - 1) * $this->per_page;
        $slice = is_array($this->items) ? array_slice($this->items, $off, $this->per_page) : $this->items->slice($off, $this->per_page)->get();
        return new Page($slice, $n, $this);
    }

    public function page_range(): array { return range(1, $this->num_pages); }
    public function tplGet(string $k): mixed
    {
        return match ($k) { 'page_range' => $this->page_range(), 'count' => $this->count, 'num_pages' => $this->num_pages, default => Undefined::get() };
    }
}

final class Page implements IteratorAggregate, Countable, ArrayAccess
{
    public function __construct(public array $object_list, public int $number, public Paginator $paginator) {}
    public function has_next(): bool { return $this->number < $this->paginator->num_pages; }
    public function has_previous(): bool { return $this->number > 1; }
    public function has_other_pages(): bool { return $this->has_next() || $this->has_previous(); }
    public function next_page_number(): int { return $this->number + 1; }
    public function previous_page_number(): int { return $this->number - 1; }
    public function start_index(): int { return $this->paginator->count === 0 ? 0 : ($this->number - 1) * $this->paginator->per_page + 1; }
    public function end_index(): int { return $this->start_index() + count($this->object_list) - ($this->paginator->count === 0 ? 0 : 1); }
    public function getIterator(): ArrayIterator { return new ArrayIterator($this->object_list); }
    public function count(): int { return count($this->object_list); }
    public function offsetExists(mixed $o): bool { return isset($this->object_list[$o]); }
    public function offsetGet(mixed $o): mixed { return $this->object_list[$o] ?? null; }
    public function offsetSet(mixed $o, mixed $v): void {}
    public function offsetUnset(mixed $o): void {}
    public function tplGet(string $k): mixed
    {
        return match ($k) { 'number' => $this->number, 'paginator' => $this->paginator, 'object_list' => $this->object_list, default => Undefined::get() };
    }
}
