<?php
/**
 * Django-compatible template engine (the subset used by US Vesta templates).
 *
 * Supports: {{ var|filter:arg }}, {% if/elif/else %}, {% for x in y [reversed] %}{% empty %},
 * {% block %}/{% extends %}, {% include %}, {% with %}, {% widthratio %}, {% csrf_token %},
 * {% static %}, {% load %}, {% comment %}, {# #}, {% provider_login_url %}, {% now %}, {% spaceless %},
 * {% verbatim %}, {% cycle %}, {% firstof %}, autoescaping and Django truthiness.
 */

final class SafeString
{
    public function __construct(public string $value) {}
    public function __toString(): string { return $this->value; }
}

final class Undefined
{
    public static ?Undefined $i = null;
    public static function get(): Undefined { return self::$i ??= new Undefined(); }
    public function __toString(): string { return ''; }
}

final class TemplateError extends RuntimeException {}

class Context
{
    /** @var array<int, array<string,mixed>> */
    private array $stack;
    public array $blocks = [];      // name => list of BlockNode (child first)
    public array $renderCache = []; // misc per-render state (cycle)

    public function __construct(array $vars) { $this->stack = [$vars]; }
    public function push(array $vars = []): void { $this->stack[] = $vars; }
    public function pop(): void { array_pop($this->stack); }
    public function set(string $k, mixed $v): void { $this->stack[count($this->stack) - 1][$k] = $v; }

    public function has(string $k): bool
    {
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            if (array_key_exists($k, $this->stack[$i])) return true;
        }
        return false;
    }

    public function get(string $k): mixed
    {
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            if (array_key_exists($k, $this->stack[$i])) return $this->stack[$i][$k];
        }
        return Undefined::get();
    }
}

/* ------------------------------------------------------------------ helpers */

final class T
{
    /** Django truthiness. */
    public static function truthy(mixed $v): bool
    {
        if ($v instanceof Undefined || $v === null || $v === false) return false;
        if ($v instanceof SafeString) return $v->value !== '';
        if (is_string($v)) return $v !== '';
        if (is_int($v) || is_float($v)) return $v != 0;
        if (is_array($v)) return count($v) > 0;
        if ($v instanceof FieldFile) return $v->name !== '';
        if ($v instanceof Countable) return count($v) > 0;
        if (is_object($v) && method_exists($v, '__bool')) return (bool)$v->__bool();
        return true;
    }

    /** Python-like str() for output. */
    public static function str(mixed $v): string
    {
        if ($v instanceof Undefined) return '';
        if ($v === null) return 'None';
        if ($v === true) return 'True';
        if ($v === false) return 'False';
        if (is_float($v)) {
            if (is_nan($v)) return 'nan';
            if (floor($v) == $v && abs($v) < 1e16) return number_format($v, 1, '.', '');
            return rtrim(rtrim(sprintf('%.15F', $v), '0'), '.') ?: '0';
        }
        if (is_array($v)) return self::pyRepr($v);
        if ($v instanceof DateTimeInterface) return $v->format('Y-m-d H:i:sP');
        if (is_object($v)) {
            if (method_exists($v, '__toString')) return (string)$v;
            return get_class($v) . ' object';
        }
        return (string)$v;
    }

    public static function pyRepr(mixed $v): string
    {
        if (is_array($v)) {
            if (array_is_list($v)) return '[' . implode(', ', array_map([self::class, 'pyRepr'], $v)) . ']';
            $p = [];
            foreach ($v as $k => $x) $p[] = self::pyRepr($k) . ': ' . self::pyRepr($x);
            return '{' . implode(', ', $p) . '}';
        }
        if (is_string($v)) return "'" . str_replace("'", "\\'", $v) . "'";
        return self::str($v);
    }

    public static function esc(mixed $v): string
    {
        if ($v instanceof SafeString) return $v->value;
        return htmlspecialchars(self::str($v), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Resolve attribute / key / index / zero-arg method like Django's Variable._resolve_lookup. */
    public static function lookup(mixed $obj, string $attr): mixed
    {
        if ($obj instanceof Undefined || $obj === null) return Undefined::get();
        if (is_array($obj)) {
            if (array_key_exists($attr, $obj)) return $obj[$attr];
            if (ctype_digit($attr) && array_key_exists((int)$attr, $obj)) return $obj[(int)$attr];
            if ($attr === 'items') return array_map(null, array_keys($obj), array_values($obj));
            if ($attr === 'keys') return array_keys($obj);
            if ($attr === 'values') return array_values($obj);
            if ($attr === 'count') return count($obj);
            return Undefined::get();
        }
        if (is_string($obj)) {
            if (ctype_digit($attr)) { $c = mb_substr($obj, (int)$attr, 1); return $c === '' ? Undefined::get() : $c; }
            if ($attr === 'upper') return mb_strtoupper($obj);
            if ($attr === 'lower') return mb_strtolower($obj);
            if ($attr === 'strip') return trim($obj);
            return Undefined::get();
        }
        if (is_object($obj)) {
            if ($obj instanceof ArrayAccess && !($obj instanceof Model) && isset($obj[$attr])) return $obj[$attr];
            $hasTpl = method_exists($obj, 'tplGet');
            if ($hasTpl) {
                $r = $obj->tplGet($attr);
                if (!($r instanceof Undefined)) return $r;
            }
            if (method_exists($obj, $attr) && !str_starts_with($attr, '_')) {
                $rm = new ReflectionMethod($obj, $attr);
                if ($rm->isPublic() && $rm->getNumberOfRequiredParameters() === 0) return $obj->$attr();
            }
            if (property_exists($obj, $attr)) return $obj->$attr;
            if ($hasTpl) return Undefined::get();   // unknown attribute -> '' like Django
            if (method_exists($obj, '__get')) {
                try { $r = $obj->$attr; return $r ?? null; } catch (Throwable) { return Undefined::get(); }
            }
            if (ctype_digit($attr) && $obj instanceof ArrayAccess) return $obj[(int)$attr] ?? Undefined::get();
        }
        return Undefined::get();
    }

    public static function iter(mixed $v): array
    {
        if ($v instanceof Undefined || $v === null || $v === false) return [];
        if (is_array($v)) return array_values($v);
        if ($v instanceof Traversable) return iterator_to_array($v, false);
        if (is_string($v)) return mb_str_split($v);
        return [];
    }

    public static function num(mixed $v): float|int|null
    {
        if ($v instanceof SafeString) $v = $v->value;
        if ($v instanceof Decimal) return (float)$v->v;
        if (is_int($v) || is_float($v)) return $v;
        if (is_bool($v)) return (int)$v;
        if (is_string($v) && is_numeric(trim($v))) return trim($v) + 0;
        return null;
    }

    public static function eq(mixed $a, mixed $b): bool
    {
        if ($a instanceof Undefined) $a = null;
        if ($b instanceof Undefined) $b = null;
        if ($a instanceof SafeString) $a = $a->value;
        if ($b instanceof SafeString) $b = $b->value;
        if ($a instanceof Decimal) $a = (float)$a->v;
        if ($b instanceof Decimal) $b = (float)$b->v;
        if ($a instanceof FieldFile) $a = $a->name;
        if ($b instanceof FieldFile) $b = $b->name;
        if ($a instanceof Model && $b instanceof Model) return get_class($a) === get_class($b) && $a->pk() == $b->pk();
        if (is_bool($a) || is_bool($b)) {
            if ((is_int($a) || is_int($b)) && !is_bool($a) || !is_bool($b) && is_int($b)) return (int)$a === (int)$b;
            return $a === $b;
        }
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) return $a == $b;
        if (is_string($a) && is_string($b)) return $a === $b;
        if ($a === null || $b === null) return $a === $b;
        // Python: '1' == 1 is False
        if ((is_string($a) && (is_int($b) || is_float($b))) || (is_string($b) && (is_int($a) || is_float($a)))) return false;
        return $a == $b;
    }

    public static function cmp(mixed $a, mixed $b): ?int
    {
        if ($a instanceof Undefined || $b instanceof Undefined || $a === null || $b === null) return null;
        if ($a instanceof SafeString) $a = $a->value;
        if ($b instanceof SafeString) $b = $b->value;
        if ($a instanceof Decimal) $a = (float)$a->v;
        if ($b instanceof Decimal) $b = (float)$b->v;
        $na = (is_int($a) || is_float($a) || is_bool($a)) ? (float)$a : null;
        $nb = (is_int($b) || is_float($b) || is_bool($b)) ? (float)$b : null;
        if ($na !== null && $nb !== null) return $na <=> $nb;
        if (is_string($a) && is_string($b)) return strcmp($a, $b) <=> 0;
        if ($a instanceof DateTimeInterface && $b instanceof DateTimeInterface) return $a <=> $b;
        return null; // TypeError in Python -> condition false
    }

    public static function contains(mixed $needle, mixed $hay): bool
    {
        if ($needle instanceof SafeString) $needle = $needle->value;
        if ($hay instanceof SafeString) $hay = $hay->value;
        if (is_string($hay)) return is_string($needle) || is_numeric($needle) ? str_contains($hay, (string)$needle) : false;
        if (is_array($hay)) {
            if (!array_is_list($hay)) return array_key_exists((string)$needle, $hay);
            foreach ($hay as $x) if (self::eq($x, $needle)) return true;
            return false;
        }
        if ($hay instanceof Traversable) {
            foreach ($hay as $x) if (self::eq($x, $needle)) return true;
        }
        return false;
    }
}

/* ------------------------------------------------------------------ nodes */

abstract class Node
{
    abstract public function render(Context $c, Engine $e): string;
}

final class TextNode extends Node
{
    public function __construct(public string $text) {}
    public function render(Context $c, Engine $e): string { return $this->text; }
}

final class VarNode extends Node
{
    public function __construct(public FilterExpr $expr) {}
    public function render(Context $c, Engine $e): string
    {
        $v = $this->expr->resolve($c, $e);
        return $e->autoescape ? T::esc($v) : T::str($v instanceof SafeString ? $v->value : $v);
    }
}

final class NodeList extends Node
{
    /** @param Node[] $nodes */
    public function __construct(public array $nodes = []) {}
    public function render(Context $c, Engine $e): string
    {
        $o = '';
        foreach ($this->nodes as $n) $o .= $n->render($c, $e);
        return $o;
    }
}

final class IfNode extends Node
{
    /** @param array<int, array{0: ?IfExpr, 1: NodeList}> $branches */
    public function __construct(public array $branches) {}
    public function render(Context $c, Engine $e): string
    {
        foreach ($this->branches as [$cond, $body]) {
            if ($cond === null || T::truthy($cond->eval($c, $e))) return $body->render($c, $e);
        }
        return '';
    }
}

final class ForNode extends Node
{
    public function __construct(
        public array $targets, public FilterExpr $seq, public bool $reversed,
        public NodeList $body, public ?NodeList $empty
    ) {}

    public function render(Context $c, Engine $e): string
    {
        $items = T::iter($this->seq->resolve($c, $e));
        if ($this->reversed) $items = array_reverse($items);
        $n = count($items);
        if ($n === 0) return $this->empty ? $this->empty->render($c, $e) : '';
        $parent = $c->get('forloop');
        $c->push();
        $o = '';
        foreach ($items as $i => $item) {
            $c->set('forloop', [
                'counter' => $i + 1, 'counter0' => $i, 'revcounter' => $n - $i, 'revcounter0' => $n - $i - 1,
                'first' => $i === 0, 'last' => $i === $n - 1, 'parentloop' => $parent instanceof Undefined ? [] : $parent,
            ]);
            if (count($this->targets) === 1) {
                $c->set($this->targets[0], $item);
            } else {
                $vals = is_array($item) ? array_values($item) : T::iter($item);
                foreach ($this->targets as $k => $t) $c->set($t, $vals[$k] ?? null);
            }
            $o .= $this->body->render($c, $e);
        }
        $c->pop();
        return $o;
    }
}

final class BlockNode extends Node
{
    public function __construct(public string $name, public NodeList $body) {}
    public function render(Context $c, Engine $e): string
    {
        $chain = $c->blocks[$this->name] ?? [];
        if (!in_array($this, $chain, true)) $chain[] = $this;   // this template's own block is the final super()
        return $this->renderChain($chain, 0, $c, $e);
    }
    public function renderChain(array $chain, int $idx, Context $c, Engine $e): string
    {
        $blk = $chain[$idx] ?? $this;
        $c->push();
        $c->set('block', new BlockSuper(fn() => isset($chain[$idx + 1]) ? $this->renderChain($chain, $idx + 1, $c, $e) : ''));
        $o = $blk->body->render($c, $e);
        $c->pop();
        return $o;
    }
}

final class BlockSuper
{
    public function __construct(private Closure $fn) {}
    public function super(): SafeString { return new SafeString(($this->fn)()); }
}

final class ExtendsNode extends Node
{
    public function __construct(public FilterExpr $parent, public NodeList $body) {}
    public function render(Context $c, Engine $e): string
    {
        // Register this template's blocks (child blocks take priority -> they are first in chain)
        foreach (Engine::collectBlocks($this->body) as $name => $blk) {
            $c->blocks[$name][] = $blk;
        }
        $parentName = T::str($this->parent->resolve($c, $e));
        $tpl = $e->load($parentName);
        return $tpl->render($c, $e);
    }
}

final class IncludeNode extends Node
{
    public function __construct(public FilterExpr $name, public array $with, public bool $only) {}
    public function render(Context $c, Engine $e): string
    {
        $name = T::str($this->name->resolve($c, $e));
        $vars = [];
        foreach ($this->with as $k => $expr) $vars[$k] = $expr->resolve($c, $e);
        $tpl = $e->load($name);
        if ($this->only) {
            $nc = new Context($vars);
            return $tpl->render($nc, $e);
        }
        $c->push($vars);
        $savedBlocks = $c->blocks;
        $c->blocks = [];
        $o = $tpl->render($c, $e);
        $c->blocks = $savedBlocks;
        $c->pop();
        return $o;
    }
}

final class WithNode extends Node
{
    public function __construct(public array $assign, public NodeList $body) {}
    public function render(Context $c, Engine $e): string
    {
        $vars = [];
        foreach ($this->assign as $k => $expr) $vars[$k] = $expr->resolve($c, $e);
        $c->push($vars);
        $o = $this->body->render($c, $e);
        $c->pop();
        return $o;
    }
}

final class WidthRatioNode extends Node
{
    public function __construct(public FilterExpr $val, public FilterExpr $max, public FilterExpr $width, public ?string $asVar) {}
    public function render(Context $c, Engine $e): string
    {
        $v = T::num($this->val->resolve($c, $e));
        $m = T::num($this->max->resolve($c, $e));
        $w = T::num($this->width->resolve($c, $e));
        $out = '';
        if ($v !== null && $m !== null && $w !== null) {
            if ($m == 0) $out = '0';
            else $out = (string)(int)Filters::pyRound(($v / $m) * $w);
        }
        if ($this->asVar) { $c->set($this->asVar, $out); return ''; }
        return $out;
    }
}

final class CallbackNode extends Node
{
    public function __construct(public Closure $fn) {}
    public function render(Context $c, Engine $e): string { return ($this->fn)($c, $e); }
}

final class AutoescapeNode extends Node
{
    public function __construct(public bool $on, public NodeList $body) {}
    public function render(Context $c, Engine $e): string
    {
        $prev = $e->autoescape;
        $e->autoescape = $this->on;
        $o = $this->body->render($c, $e);
        $e->autoescape = $prev;
        return $o;
    }
}

/* ------------------------------------------------------------------ expressions */

final class FilterExpr
{
    /** @param array<int, array{0:string, 1:?array}> $filters  [name, argToken|null] */
    public function __construct(public array $base, public array $filters) {}

    /** base: ['lit', value] | ['var', [parts]] */
    public function resolve(Context $c, Engine $e, bool $ignoreFailures = false): mixed
    {
        $v = self::resolveToken($this->base, $c);
        foreach ($this->filters as [$name, $arg]) {
            $a = $arg === null ? Undefined::get() : self::resolveToken($arg, $c);
            $v = Filters::apply($name, $v, $a, $arg !== null, $e);
        }
        return $v;
    }

    public static function resolveToken(array $tok, Context $c): mixed
    {
        if ($tok[0] === 'lit') return $tok[1];
        $parts = $tok[1];
        $first = array_shift($parts);
        $v = $c->get($first);
        foreach ($parts as $p) {
            $v = T::lookup($v, $p);
            if ($v instanceof Undefined) return $v;
        }
        return $v;
    }

    public static function parse(string $s): FilterExpr
    {
        $s = trim($s);
        $toks = self::splitFilters($s);
        $base = self::parseOperand(array_shift($toks));
        $filters = [];
        foreach ($toks as $f) {
            $f = trim($f);
            $colon = self::findColon($f);
            if ($colon === false) $filters[] = [trim($f), null];
            else $filters[] = [trim(substr($f, 0, $colon)), self::parseOperand(trim(substr($f, $colon + 1)))];
        }
        return new FilterExpr($base, $filters);
    }

    private static function findColon(string $f): int|false
    {
        $q = null;
        for ($i = 0, $n = strlen($f); $i < $n; $i++) {
            $ch = $f[$i];
            if ($q) { if ($ch === $q) $q = null; continue; }
            if ($ch === '"' || $ch === "'") { $q = $ch; continue; }
            if ($ch === ':') return $i;
        }
        return false;
    }

    private static function splitFilters(string $s): array
    {
        $out = []; $cur = ''; $q = null;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $ch = $s[$i];
            if ($q) { $cur .= $ch; if ($ch === $q) $q = null; continue; }
            if ($ch === '"' || $ch === "'") { $q = $ch; $cur .= $ch; continue; }
            if ($ch === '|') { $out[] = $cur; $cur = ''; continue; }
            $cur .= $ch;
        }
        $out[] = $cur;
        return $out;
    }

    public static function parseOperand(string $t): array
    {
        $t = trim($t);
        if ($t === '') return ['lit', ''];
        if (preg_match('/^_\((.*)\)$/s', $t, $m)) $t = trim($m[1]);
        $f = $t[0];
        if (($f === '"' || $f === "'") && strlen($t) >= 2 && substr($t, -1) === $f) {
            // Django unescape_string_literal(): only \<quote> and \\ are unescaped
            $inner = substr($t, 1, -1);
            $inner = str_replace(['\\' . $f, '\\\\'], [$f, '\\'], $inner);
            return ['lit', new SafeString($inner)];
        }
        if (preg_match('/^-?\d+$/', $t)) return ['lit', (int)$t];
        if (preg_match('/^-?\d*\.\d+(e-?\d+)?$/i', $t)) return ['lit', (float)$t];
        if ($t === 'True') return ['lit', true];
        if ($t === 'False') return ['lit', false];
        if ($t === 'None') return ['lit', null];
        return ['var', explode('.', $t)];
    }
}

/** Boolean expression for {% if %} (Django smartif precedence). */
final class IfExpr
{
    private array $tokens;
    private int $pos = 0;
    private array $ast;

    public function __construct(string $src)
    {
        $this->tokens = self::tokenize($src);
        $this->ast = $this->parseExpr(0);
        if ($this->pos < count($this->tokens)) {
            throw new TemplateError("Unused tokens in if: $src");
        }
    }

    private const BP = ['or' => 6, 'and' => 7, 'not' => 8, 'in' => 9, 'not in' => 9, 'is' => 10, 'is not' => 10,
        '==' => 10, '!=' => 10, '>' => 10, '>=' => 10, '<' => 10, '<=' => 10];

    private static function tokenize(string $s): array
    {
        $raw = [];
        $cur = ''; $q = null;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $ch = $s[$i];
            if ($q) { $cur .= $ch; if ($ch === $q) $q = null; continue; }
            if ($ch === '"' || $ch === "'") { $q = $ch; $cur .= $ch; continue; }
            if (ctype_space($ch)) { if ($cur !== '') { $raw[] = $cur; $cur = ''; } continue; }
            $cur .= $ch;
        }
        if ($cur !== '') $raw[] = $cur;
        // split operators glued to operands e.g. a==b
        $toks = [];
        foreach ($raw as $r) {
            if ($r[0] !== '"' && $r[0] !== "'" && preg_match('/^(.+?)(==|!=|>=|<=|>|<)(.+)$/', $r, $m) && !str_contains($m[1], ':')) {
                array_push($toks, $m[1], $m[2], $m[3]);
            } else $toks[] = $r;
        }
        $out = [];
        for ($i = 0; $i < count($toks); $i++) {
            if ($toks[$i] === 'not' && ($toks[$i + 1] ?? '') === 'in') { $out[] = 'not in'; $i++; continue; }
            if ($toks[$i] === 'is' && ($toks[$i + 1] ?? '') === 'not') { $out[] = 'is not'; $i++; continue; }
            $out[] = $toks[$i];
        }
        return $out;
    }

    private function peek(): ?string { return $this->tokens[$this->pos] ?? null; }

    private function parseExpr(int $rbp): array
    {
        $t = $this->tokens[$this->pos++] ?? null;
        if ($t === null) throw new TemplateError('Unexpected end of if expression');
        if ($t === 'not') $left = ['not', $this->parseExpr(self::BP['not'])];
        else $left = ['val', FilterExpr::parse($t)];
        while (($op = $this->peek()) !== null && isset(self::BP[$op]) && $op !== 'not' && self::BP[$op] > $rbp) {
            $this->pos++;
            $right = $this->parseExpr(self::BP[$op]);
            $left = ['op', $op, $left, $right];
        }
        return $left;
    }

    public function eval(Context $c, Engine $e): mixed { return $this->ev($this->ast, $c, $e); }

    private function ev(array $n, Context $c, Engine $e): mixed
    {
        switch ($n[0]) {
            case 'val': return $n[1]->resolve($c, $e);
            case 'not': return !T::truthy($this->ev($n[1], $c, $e));
        }
        [, $op, $l, $r] = $n;
        if ($op === 'or') { $a = $this->ev($l, $c, $e); return T::truthy($a) ? $a : $this->ev($r, $c, $e); }
        if ($op === 'and') { $a = $this->ev($l, $c, $e); return !T::truthy($a) ? $a : $this->ev($r, $c, $e); }
        $a = $this->ev($l, $c, $e); $b = $this->ev($r, $c, $e);
        return match ($op) {
            '==' => T::eq($a, $b),
            '!=' => !T::eq($a, $b),
            'is' => ($a instanceof Undefined ? null : $a) === ($b instanceof Undefined ? null : $b),
            'is not' => ($a instanceof Undefined ? null : $a) !== ($b instanceof Undefined ? null : $b),
            'in' => T::contains($a, $b),
            'not in' => !T::contains($a, $b),
            '>' => ($x = T::cmp($a, $b)) !== null && $x > 0,
            '>=' => ($x = T::cmp($a, $b)) !== null && $x >= 0,
            '<' => ($x = T::cmp($a, $b)) !== null && $x < 0,
            '<=' => ($x = T::cmp($a, $b)) !== null && $x <= 0,
        };
    }
}

/* ------------------------------------------------------------------ parser */

final class Parser
{
    private array $tokens; // [type, content, line]
    private int $pos = 0;

    public function __construct(private string $src, private string $name) { $this->tokens = $this->lex($src); }

    private function lex(string $s): array
    {
        $out = [];
        $re = '/(\{\{.*?\}\}|\{%.*?%\}|\{#.*?#\})/';
        $parts = preg_split($re, $s, -1, PREG_SPLIT_DELIM_CAPTURE);
        $verbatim = null;
        foreach ($parts as $p) {
            if ($p === '') continue;
            if ($verbatim !== null) {
                if (preg_match('/^\{%\s*endverbatim\s*%\}$/', $p)) { $out[] = ['text', $verbatim]; $verbatim = null; }
                else $verbatim .= $p;
                continue;
            }
            if (str_starts_with($p, '{{') && str_ends_with($p, '}}')) $out[] = ['var', trim(substr($p, 2, -2))];
            elseif (str_starts_with($p, '{%') && str_ends_with($p, '%}')) {
                $inner = trim(substr($p, 2, -2));
                if (preg_match('/^verbatim\b/', $inner)) { $verbatim = ''; continue; }
                $out[] = ['block', $inner];
            } elseif (str_starts_with($p, '{#') && str_ends_with($p, '#}')) continue;
            else $out[] = ['text', $p];
        }
        return $out;
    }

    public function parse(): NodeList
    {
        $nl = $this->parseUntil([]);
        // extends must wrap everything
        foreach ($nl->nodes as $i => $n) {
            if ($n instanceof ExtendsNode) {
                $rest = array_slice($nl->nodes, $i + 1);
                $n->body = new NodeList($rest);
                return new NodeList([$n]);
            }
        }
        return $nl;
    }

    /** @return NodeList  and sets $this->lastEnd to the end tag found */
    public ?string $lastEnd = null;
    public ?string $lastEndContent = null;

    private function parseUntil(array $ends): NodeList
    {
        $nodes = [];
        while ($this->pos < count($this->tokens)) {
            [$type, $content] = $this->tokens[$this->pos++];
            if ($type === 'text') { $nodes[] = new TextNode($content); continue; }
            if ($type === 'var') {
                if ($content === '') { continue; }
                $nodes[] = new VarNode(FilterExpr::parse($content));
                continue;
            }
            $bits = preg_split('/\s+/', $content, 2);
            $tag = $bits[0];
            $args = $bits[1] ?? '';
            if (in_array($tag, $ends, true)) {
                $this->lastEnd = $tag;
                $this->lastEndContent = $args;
                return new NodeList($nodes);
            }
            $node = $this->tag($tag, $args);
            if ($node) $nodes[] = $node;
        }
        if ($ends) throw new TemplateError("{$this->name}: unclosed tag, expected one of " . implode(',', $ends));
        return new NodeList($nodes);
    }

    private function tag(string $tag, string $args): ?Node
    {
        switch ($tag) {
            case 'if':
                $branches = [];
                $cond = new IfExpr($args);
                while (true) {
                    $body = $this->parseUntil(['elif', 'else', 'endif']);
                    $branches[] = [$cond, $body];
                    if ($this->lastEnd === 'endif') break;
                    if ($this->lastEnd === 'else') {
                        $body = $this->parseUntil(['endif']);
                        $branches[] = [null, $body];
                        break;
                    }
                    $cond = new IfExpr($this->lastEndContent);
                }
                return new IfNode($branches);

            case 'for':
                if (!preg_match('/^(.+?)\s+in\s+(.+?)(\s+reversed)?$/s', trim($args), $m)) throw new TemplateError("bad for: $args");
                $targets = array_map('trim', explode(',', $m[1]));
                $seq = FilterExpr::parse($m[2]);
                $body = $this->parseUntil(['empty', 'endfor']);
                $empty = null;
                if ($this->lastEnd === 'empty') $empty = $this->parseUntil(['endfor']);
                return new ForNode($targets, $seq, !empty($m[3]), $body, $empty);

            case 'block':
                $name = trim(preg_split('/\s+/', $args)[0]);
                $body = $this->parseUntil(['endblock']);
                return new BlockNode($name, $body);

            case 'extends':
                return new ExtendsNode(FilterExpr::parse($args), new NodeList());

            case 'include':
                $only = false; $with = [];
                if (preg_match('/\s+only$/', $args)) { $only = true; $args = preg_replace('/\s+only$/', '', $args); }
                $parts = preg_split('/\s+with\s+/', $args, 2);
                if (isset($parts[1])) $with = self::kwargs($parts[1]);
                return new IncludeNode(FilterExpr::parse($parts[0]), $with, $only);

            case 'with':
                $assign = self::kwargs($args);
                $body = $this->parseUntil(['endwith']);
                return new WithNode($assign, $body);

            case 'comment':
                $this->skipUntil('endcomment');
                return null;

            case 'load':
                return null;

            case 'csrf_token':
                return new CallbackNode(fn() => '<input type="hidden" name="csrfmiddlewaretoken" value="' . htmlspecialchars(csrf_token()) . '">');

            case 'static':
                $p = preg_split('/\s+as\s+/', trim($args));
                $expr = FilterExpr::parse($p[0]);
                $as = $p[1] ?? null;
                return new CallbackNode(function (Context $c, Engine $e) use ($expr, $as) {
                    $url = '/static/' . ltrim(T::str($expr->resolve($c, $e)), '/');
                    if ($as) { $c->set(trim($as), $url); return ''; }
                    return htmlspecialchars($url, ENT_QUOTES);
                });

            case 'provider_login_url':
                $p = preg_split('/\s+/', trim($args));
                $prov = FilterExpr::parse($p[0]);
                $kw = self::kwargs(implode(' ', array_slice($p, 1)));
                return new CallbackNode(function (Context $c, Engine $e) use ($prov, $kw) {
                    $url = '/accounts/' . T::str($prov->resolve($c, $e)) . '/login/';
                    $q = [];
                    foreach ($kw as $k => $v) $q[$k] = T::str($v->resolve($c, $e));
                    if ($q) $url .= '?' . http_build_query($q);
                    return htmlspecialchars($url, ENT_QUOTES);
                });

            case 'url':
                $p = preg_split('/\s+/', trim($args));
                $name = FilterExpr::parse(array_shift($p));
                return new CallbackNode(fn(Context $c, Engine $e) => htmlspecialchars(url_for(T::str($name->resolve($c, $e))), ENT_QUOTES));

            case 'widthratio':
                $p = preg_split('/\s+/', trim($args));
                $as = null;
                if (count($p) === 5 && $p[3] === 'as') $as = $p[4];
                return new WidthRatioNode(FilterExpr::parse($p[0]), FilterExpr::parse($p[1]), FilterExpr::parse($p[2]), $as);

            case 'now':
                $fmt = FilterExpr::parse($args);
                return new CallbackNode(fn(Context $c, Engine $e) => Filters::djangoDate(now_local(), T::str($fmt->resolve($c, $e))));

            case 'spaceless':
                $body = $this->parseUntil(['endspaceless']);
                return new CallbackNode(fn(Context $c, Engine $e) => trim(preg_replace('/>\s+</', '><', $body->render($c, $e))));

            case 'autoescape':
                $body = $this->parseUntil(['endautoescape']);
                return new AutoescapeNode(trim($args) === 'on', $body);

            case 'firstof':
                $exprs = array_map([FilterExpr::class, 'parse'], self::splitArgs($args));
                return new CallbackNode(function (Context $c, Engine $e) use ($exprs) {
                    foreach ($exprs as $x) { $v = $x->resolve($c, $e); if (T::truthy($v)) return T::esc($v); }
                    return '';
                });

            case 'cycle':
                $exprs = array_map([FilterExpr::class, 'parse'], self::splitArgs($args));
                $id = spl_object_id((object)[]) . mt_rand();
                return new CallbackNode(function (Context $c, Engine $e) use ($exprs, $id) {
                    $i = $c->renderCache[$id] ?? 0;
                    $c->renderCache[$id] = $i + 1;
                    return T::esc($exprs[$i % count($exprs)]->resolve($c, $e));
                });

            case 'filter':
                $chain = FilterExpr::parse('x|' . $args);
                $body = $this->parseUntil(['endfilter']);
                return new CallbackNode(function (Context $c, Engine $e) use ($chain, $body) {
                    $c->push(['x' => new SafeString($body->render($c, $e))]);
                    $v = $chain->resolve($c, $e);
                    $c->pop();
                    return T::esc($v);
                });

            case 'endif': case 'endfor': case 'endblock': case 'endwith': case 'else': case 'elif': case 'empty': case 'endcomment':
                // stray end tag (e.g. mismatched template) – ignore like a lenient renderer
                return null;
        }
        throw new TemplateError("{$this->name}: unknown tag '{$tag}'");
    }

    private function skipUntil(string $end): void
    {
        while ($this->pos < count($this->tokens)) {
            [$type, $content] = $this->tokens[$this->pos++];
            if ($type === 'block' && preg_split('/\s+/', $content)[0] === $end) return;
        }
    }

    private static function splitArgs(string $s): array
    {
        preg_match_all('/"[^"]*"|\'[^\']*\'|\S+/', $s, $m);
        return $m[0];
    }

    /** "a=b c=d" or "b as a" */
    private static function kwargs(string $s): array
    {
        $s = trim($s);
        $out = [];
        if ($s === '') return $out;
        if (preg_match('/^(.+?)\s+as\s+(\w+)$/s', $s, $m) && !str_contains($m[1], '=')) {
            $out[$m[2]] = FilterExpr::parse($m[1]);
            return $out;
        }
        foreach (self::splitArgs($s) as $pair) {
            $eq = strpos($pair, '=');
            if ($eq === false) continue;
            $out[trim(substr($pair, 0, $eq))] = FilterExpr::parse(substr($pair, $eq + 1));
        }
        return $out;
    }
}

final class Template
{
    public function __construct(public string $name, public NodeList $root) {}
    public function render(Context $c, Engine $e): string { return $this->root->render($c, $e); }
}

final class Engine
{
    public bool $autoescape = true;
    private array $cache = [];

    public function __construct(private string $dir, private ?string $cacheDir = null) {}

    public function load(string $name): Template
    {
        if (isset($this->cache[$name])) return $this->cache[$name];
        $path = $this->dir . '/' . ltrim($name, '/');
        if (!is_file($path)) throw new TemplateError("Template not found: $name");
        $cacheFile = null;
        if ($this->cacheDir) {
            $cacheFile = $this->cacheDir . '/tpl_' . md5($path) . '_' . filemtime($path) . '.ser';
            if (is_file($cacheFile)) {
                $t = @unserialize(file_get_contents($cacheFile));
                if ($t instanceof Template) return $this->cache[$name] = $t;
            }
        }
        $root = (new Parser(file_get_contents($path), $name))->parse();
        $t = new Template($name, $root);
        if ($cacheFile && !self::hasClosures($root)) @file_put_contents($cacheFile, serialize($t));
        return $this->cache[$name] = $t;
    }

    private static function hasClosures(Node $n): bool { return true; } // closures aren't serializable: keep it in-memory only

    public function render(string $name, array $vars): string
    {
        $c = new Context($vars);
        return $this->load($name)->render($c, $this);
    }

    public static function collectBlocks(Node $n): array
    {
        $out = [];
        $walk = function (Node $n) use (&$walk, &$out) {
            if ($n instanceof BlockNode) { $out[$n->name] = $n; $walk($n->body); return; }
            if ($n instanceof NodeList) foreach ($n->nodes as $x) $walk($x);
            elseif ($n instanceof IfNode) foreach ($n->branches as [, $b]) $walk($b);
            elseif ($n instanceof ForNode) { $walk($n->body); if ($n->empty) $walk($n->empty); }
            elseif ($n instanceof WithNode || $n instanceof AutoescapeNode) $walk($n->body);
        };
        $walk($n);
        return $out;
    }
}

/* ------------------------------------------------------------------ filters */

final class Filters
{
    public static function pyRound(float $x, int $d = 0): float
    {
        // Python 3 banker's rounding
        $m = 10 ** $d;
        $v = $x * $m;
        $r = round($v, 0, PHP_ROUND_HALF_EVEN);
        return $r / $m;
    }

    public static function apply(string $name, mixed $v, mixed $arg, bool $hasArg, Engine $e): mixed
    {
        switch ($name) {
            case 'default':
                return T::truthy($v) ? $v : $arg;
            case 'default_if_none':
                return ($v === null || $v instanceof Undefined) ? $arg : $v;
            case 'safe':
                return new SafeString(T::str($v instanceof SafeString ? $v->value : $v));
            case 'escape':
                return new SafeString(T::esc($v));
            case 'length':
                if (is_array($v)) return count($v);
                if ($v instanceof Countable) return count($v);
                if (is_string($v) || $v instanceof SafeString) return mb_strlen((string)$v);
                return 0;
            case 'lower': return self::keepSafe($v, mb_strtolower(T::str($v)));
            case 'upper': return self::keepSafe($v, mb_strtoupper(T::str($v)));
            case 'title':
                return self::keepSafe($v, preg_replace_callback("/([a-z])'([A-Z])/", fn($m) => strtolower($m[0]), mb_convert_case(T::str($v), MB_CASE_TITLE)));
            case 'capfirst':
                $s = T::str($v); return self::keepSafe($v, mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1));
            case 'truncatechars':
                $n = (int)T::num($arg);
                $s = T::str($v instanceof SafeString ? $v->value : $v);
                if ($v instanceof Undefined) return '';
                if (mb_strlen($s) <= $n) return $s;
                return mb_substr($s, 0, max(0, $n - 1)) . '…';
            case 'truncatechars_html':
                $n = (int)T::num($arg);
                $html = T::str($v instanceof SafeString ? $v->value : $v);
                return new SafeString(self::truncateHtml($html, $n));
            case 'truncatewords':
                $n = (int)T::num($arg);
                $w = preg_split('/\s+/', trim(T::str($v)));
                return count($w) > $n ? implode(' ', array_slice($w, 0, $n)) . ' …' : implode(' ', $w);
            case 'striptags':
                return strip_tags(T::str($v instanceof SafeString ? $v->value : $v));
            case 'add':
                $a = T::num($v); $b = T::num($arg);
                if ($a !== null && $b !== null && is_int($a + 0) && !is_float($a) && !is_float($b)) return (int)$a + (int)$b;
                if (is_int(self::intish($v)) && is_int(self::intish($arg))) return self::intish($v) + self::intish($arg);
                if ((is_string($v) || $v instanceof SafeString) && (is_string($arg) || $arg instanceof SafeString)) return (string)$v . (string)$arg;
                if (is_array($v) && is_array($arg)) return array_merge($v, $arg);
                return '';
            case 'floatformat':
                return self::floatformat($v, $hasArg ? $arg : null);
            case 'stringformat':
                $fmt = T::str($arg);
                $x = $v instanceof SafeString ? $v->value : $v;
                if ($fmt === 's') return T::str($x);
                if (preg_match('/[dfFeExXio]$/', $fmt)) {
                    $n = T::num($x);
                    if ($n === null) return '';
                    if (str_ends_with($fmt, 'd') || str_ends_with($fmt, 'i')) return sprintf('%' . substr($fmt, 0, -1) . 'd', (int)$n);
                    return sprintf('%' . $fmt, $n);
                }
                return @sprintf('%' . $fmt, T::str($x));
            case 'escapejs':
                return self::escapejs(T::str($v instanceof SafeString ? $v->value : $v));
            case 'date':
                $d = self::toDate($v);
                if (!$d) return '';
                return self::djangoDate($d, $hasArg ? T::str($arg) : 'N j, Y');
            case 'time':
                $d = self::toDate($v);
                if (!$d) return '';
                return self::djangoDate($d, $hasArg ? T::str($arg) : 'P');
            case 'slice':
                return self::slice($v, T::str($arg));
            case 'first':
                $it = T::iter($v); return $it[0] ?? '';
            case 'last':
                $it = T::iter($v); return $it ? end($it) : '';
            case 'join':
                return implode(T::str($arg), array_map([T::class, 'str'], T::iter($v)));
            case 'linebreaksbr':
                $s = $v instanceof SafeString ? $v->value : T::esc($v);
                return new SafeString(str_replace(["\r\n", "\n"], '<br>', $s));
            case 'linebreaks':
                $s = $v instanceof SafeString ? $v->value : T::esc($v);
                $paras = preg_split('/\n{2,}/', str_replace("\r\n", "\n", $s));
                return new SafeString(implode("\n\n", array_map(fn($p) => '<p>' . str_replace("\n", '<br>', $p) . '</p>', $paras)));
            case 'urlencode':
                return rawurlencode(T::str($v));
            case 'yesno':
                $opts = explode(',', $hasArg ? T::str($arg) : 'yes,no,maybe');
                if ($v === null || $v instanceof Undefined) return $opts[2] ?? $opts[1];
                return T::truthy($v) ? $opts[0] : $opts[1];
            case 'divisibleby':
                $a = T::num($v); $b = T::num($arg); return $a !== null && $b ? ((int)$a % (int)$b === 0) : false;
            case 'json_script':
                $id = T::str($arg);
                return new SafeString('<script id="' . htmlspecialchars($id) . '" type="application/json">' . json_encode(to_plain($v), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . '</script>');
            case 'dictsort':
                $items = T::iter($v);
                $k = T::str($arg);
                usort($items, fn($a, $b) => T::cmp(T::lookup($a, $k), T::lookup($b, $k)) ?? 0);
                return $items;
            case 'pluralize':
                $n = $v instanceof Countable || is_array($v) ? count($v) : T::num($v);
                $p = explode(',', $hasArg ? T::str($arg) : 's');
                if (count($p) === 1) array_unshift($p, '');
                return $n == 1 ? $p[0] : $p[1];
            case 'intcomma':
                $n = T::num($v); return $n === null ? T::str($v) : number_format($n, is_float($n) ? 2 : 0);
            case 'make_list':
                return mb_str_split(T::str($v));
            case 'center': case 'ljust': case 'rjust':
                return T::str($v);
            case 'random':
                $it = T::iter($v); return $it ? $it[array_rand($it)] : '';
            case 'wordcount':
                return count(preg_split('/\s+/', trim(T::str($v)), -1, PREG_SPLIT_NO_EMPTY));
            case 'cut':
                return str_replace(T::str($arg), '', T::str($v));
            case 'phone2numeric': case 'iriencode': case 'force_escape':
                return T::esc($v);
        }
        throw new TemplateError("Unknown filter: $name");
    }

    private static function intish(mixed $v): mixed
    {
        if ($v instanceof SafeString) $v = $v->value;
        if (is_int($v)) return $v;
        if (is_bool($v)) return (int)$v;
        if (is_string($v) && preg_match('/^\s*-?\d+\s*$/', $v)) return (int)$v;
        return null;
    }

    private static function keepSafe(mixed $orig, string $new): mixed
    {
        return $orig instanceof SafeString ? new SafeString($new) : $new;
    }

    public static function floatformat(mixed $v, mixed $arg): string
    {
        $raw = $v instanceof SafeString ? $v->value : $v;
        if ($raw instanceof Undefined || $raw === null) return '';
        $n = T::num($raw);
        if ($n === null) return '';
        $a = $arg === null ? '-1' : T::str($arg);
        $grouping = false;
        if (str_ends_with($a, 'g')) { $grouping = true; $a = substr($a, 0, -1); }
        if ($a === '') $a = '-1';
        $p = (int)$a;
        $f = (float)$n;
        $places = abs($p);
        $rounded = round($f, $places, PHP_ROUND_HALF_UP);
        if ($p < 0 && floor($f) == $f) {
            return number_format($f, 0, '.', $grouping ? ',' : '');
        }
        if ($p < 0 && round($f, $places) == floor(round($f, $places)) && floor($f) != $f) {
            // e.g. 34.0001|floatformat -> 34
            return number_format(round($f, $places), 0, '.', $grouping ? ',' : '');
        }
        $s = number_format($rounded, $places, '.', $grouping ? ',' : '');
        if ($s === '-0' || preg_match('/^-0\.?0*$/', $s)) $s = ltrim($s, '-');
        return $s;
    }

    public static function escapejs(string $s): SafeString
    {
        $map = ['\\' => '\\u005C', "'" => '\\u0027', '"' => '\\u0022', '>' => '\\u003E', '<' => '\\u003C', '&' => '\\u0026',
            '=' => '\\u003D', '-' => '\\u002D', ';' => '\\u003B', '`' => '\\u0060', "\u{2028}" => '\\u2028', "\u{2029}" => '\\u2029'];
        $out = strtr($s, $map);
        $out = preg_replace_callback('/[\x00-\x1f]/', fn($m) => sprintf('\\u%04X', ord($m[0])), $out);
        return new SafeString($out);
    }

    public static function toDate(mixed $v): ?DateTimeInterface
    {
        if ($v instanceof DateTimeInterface) return $v;
        if ($v instanceof SafeString) $v = $v->value;
        if (is_string($v) && $v !== '') {
            try { return to_local(new DateTimeImmutable($v, new DateTimeZone('UTC'))); } catch (Throwable) { return null; }
        }
        return null;
    }

    public static function djangoDate(DateTimeInterface $d, string $fmt): string
    {
        $named = ['DATE_FORMAT' => 'N j, Y', 'DATETIME_FORMAT' => 'N j, Y, P', 'SHORT_DATE_FORMAT' => 'm/d/Y', 'SHORT_DATETIME_FORMAT' => 'm/d/Y P', 'TIME_FORMAT' => 'P'];
        if (isset($named[$fmt])) $fmt = $named[$fmt];
        $out = '';
        $len = strlen($fmt);
        for ($i = 0; $i < $len; $i++) {
            $ch = $fmt[$i];
            if ($ch === '\\' && $i + 1 < $len) { $out .= $fmt[++$i]; continue; }
            $out .= match ($ch) {
                'a' => $d->format('a') === 'am' ? 'a.m.' : 'p.m.',
                'A' => $d->format('A'),
                'b' => strtolower($d->format('M')),
                'c' => $d->format('c'),
                'd' => $d->format('d'),
                'D' => $d->format('D'),
                'E', 'F' => $d->format('F'),
                'f' => (int)$d->format('i') === 0 ? $d->format('g') : $d->format('g:i'),
                'g' => $d->format('g'),
                'G' => $d->format('G'),
                'h' => $d->format('h'),
                'H' => $d->format('H'),
                'i' => $d->format('i'),
                'j' => $d->format('j'),
                'l' => $d->format('l'),
                'L' => $d->format('L') ? 'True' : 'False',
                'm' => $d->format('m'),
                'M' => $d->format('M'),
                'n' => $d->format('n'),
                'N' => self::apMonth((int)$d->format('n')),
                'O' => $d->format('O'),
                'P' => self::pTime($d),
                'r' => $d->format('r'),
                's' => $d->format('s'),
                'S' => $d->format('S'),
                't' => $d->format('t'),
                'T' => $d->format('T'),
                'u' => $d->format('u'),
                'U' => $d->format('U'),
                'w' => $d->format('w'),
                'W' => (string)(int)$d->format('W'),
                'y' => $d->format('y'),
                'Y' => $d->format('Y'),
                'z' => (string)((int)$d->format('z') + 1),
                'Z' => $d->format('Z'),
                default => $ch,
            };
        }
        return $out;
    }

    private static function apMonth(int $m): string
    {
        return ['Jan.', 'Feb.', 'March', 'April', 'May', 'June', 'July', 'Aug.', 'Sept.', 'Oct.', 'Nov.', 'Dec.'][$m - 1];
    }

    private static function pTime(DateTimeInterface $d): string
    {
        $h = (int)$d->format('G'); $m = (int)$d->format('i');
        if ($h === 0 && $m === 0) return 'midnight';
        if ($h === 12 && $m === 0) return 'noon';
        $f = $m === 0 ? $d->format('g') : $d->format('g:i');
        return $f . ' ' . ($h < 12 ? 'a.m.' : 'p.m.');
    }

    private static function slice(mixed $v, string $spec): mixed
    {
        $isStr = is_string($v) || $v instanceof SafeString;
        $items = $isStr ? mb_str_split((string)$v) : T::iter($v);
        $n = count($items);
        $p = explode(':', $spec);
        if (count($p) === 1) {
            $i = (int)$p[0]; if ($i < 0) $i += $n;
            $r = $items[$i] ?? '';
            return $r;
        }
        $start = $p[0] === '' ? 0 : (int)$p[0];
        $end = ($p[1] ?? '') === '' ? $n : (int)$p[1];
        if ($start < 0) $start = max(0, $n + $start);
        if ($end < 0) $end = max(0, $n + $end);
        $start = min($start, $n); $end = min($end, $n);
        $res = array_slice($items, $start, max(0, $end - $start));
        return $isStr ? implode('', $res) : $res;
    }

    private static function truncateHtml(string $html, int $n): string
    {
        $text = '';
        $count = 0;
        $open = [];
        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $part) {
            if ($part === '') continue;
            if ($part[0] === '<') {
                if (preg_match('/^<\s*\/\s*(\w+)/', $part, $m)) { array_pop($open); }
                elseif (preg_match('/^<\s*(\w+)[^>]*?(\/?)>$/', $part, $m) && $m[2] === '' && !in_array(strtolower($m[1]), ['br', 'img', 'hr', 'input', 'meta', 'link'])) $open[] = $m[1];
                $text .= $part;
                continue;
            }
            $plain = html_entity_decode($part);
            $len = mb_strlen($plain);
            if ($count + $len > $n) {
                $text .= htmlspecialchars(mb_substr($plain, 0, max(0, $n - $count - 1))) . '…';
                foreach (array_reverse($open) as $t) $text .= "</$t>";
                return $text;
            }
            $count += $len;
            $text .= $part;
        }
        return $text;
    }
}
