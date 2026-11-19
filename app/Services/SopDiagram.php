<?php

namespace App\Services;

/**
 * Renders a SOP document (see SopService) as a swimlane SVG. The picture is always
 * drawn from the data; nothing is stored.
 *
 * Layout: one lane per role (label on the left, vertical), columns by longest path
 * over the non-delayed edges. Styling follows the Alur-SOP reference: white cards
 * with a red number badge, light-blue decisions, pink cards for problem findings.
 */
class SopDiagram
{
    private const LABEL_W = 44;

    private const LANE_H = 132;

    private const COL_W = 208;

    private const NODE_W = 168;

    private const NODE_H = 66;

    private const PAD = 24;

    /** @param  array<string, mixed>  $doc */
    public function render(array $doc): string
    {
        $steps = collect($doc['steps'] ?? [])->keyBy('key');
        if ($steps->isEmpty()) {
            return $this->svg(480, 120, '<text x="240" y="64" text-anchor="middle" class="muted">Belum ada langkah.</text>');
        }

        $edges = collect($doc['edges'] ?? [])->filter(fn ($e) => $steps->has($e['from']) && $steps->has($e['to']))->values();
        $lanes = collect($doc['roles'] ?? [])->values()->all();
        if ($steps->contains(fn ($s) => empty($s['role']) || ! in_array($s['role'], $lanes, true))) {
            $lanes[] = 'Tanpa peran';
        }
        $laneOf = fn (array $s) => ($i = array_search($s['role'] ?? null, $lanes, true)) === false ? count($lanes) - 1 : $i;

        $cols = $this->columns($steps->keys()->all(), $edges->all());
        $maxCol = max($cols);
        $width = self::LABEL_W + self::PAD * 2 + ($maxCol + 1) * self::COL_W;
        $height = count($lanes) * self::LANE_H;

        $pos = [];
        foreach ($steps as $key => $s) {
            $lane = $laneOf($s);
            $cx = self::LABEL_W + self::PAD + $cols[$key] * self::COL_W + self::COL_W / 2;
            $cy = $lane * self::LANE_H + self::LANE_H / 2;
            $pos[$key] = ['cx' => $cx, 'cy' => $cy, 'lane' => $lane, 'col' => $cols[$key]];
        }

        $problemOnly = $steps->keys()->filter(function ($key) use ($edges) {
            $in = $edges->where('to', $key);

            return $in->isNotEmpty() && $in->every(fn ($e) => ($e['type'] ?? 'normal') === 'problem');
        })->all();

        $out = '';
        foreach ($lanes as $i => $name) {
            $y = $i * self::LANE_H;
            $out .= sprintf('<rect x="0" y="%d" width="%d" height="%d" class="lane lane%d"/>', $y, $width, self::LANE_H, $i % 2);
            $out .= sprintf('<rect x="0" y="%d" width="%d" height="%d" class="lane-label-bg"/>', $y, self::LABEL_W, self::LANE_H);
            $out .= sprintf(
                '<text transform="translate(%d %d) rotate(-90)" text-anchor="middle" class="lane-label">%s</text>',
                self::LABEL_W / 2 + 5, $y + self::LANE_H / 2, e($this->clip($name, 18)),
            );
        }

        foreach ($edges as $e) {
            $out .= $this->edge($e, $pos);
        }
        foreach ($steps as $key => $s) {
            $out .= $this->node($key, $s, $pos[$key], in_array($key, $problemOnly, true));
        }

        return $this->svg($width, $height, $out);
    }

    /**
     * Longest-path columns. Edges are added normal first, then problem, then delayed,
     * and an edge that would close a loop is left out of the layout (it is still drawn).
     *
     * @param  list<string>  $keys
     * @param  list<array<string, mixed>>  $edges
     * @return array<string, int>
     */
    private function columns(array $keys, array $edges): array
    {
        $order = ['normal' => 0, 'problem' => 1, 'delayed' => 2];
        usort($edges, fn ($a, $b) => ($order[$a['type'] ?? 'normal'] ?? 0) <=> ($order[$b['type'] ?? 'normal'] ?? 0));

        $next = array_fill_keys($keys, []);
        $forward = [];
        foreach ($edges as $e) {
            if ($e['from'] === $e['to'] || $this->reaches($next, $e['to'], $e['from'])) {
                continue;
            }
            $next[$e['from']][] = $e['to'];
            $forward[] = $e;
        }

        $col = array_fill_keys($keys, 0);
        for ($pass = 0; $pass < count($keys); $pass++) {
            $changed = false;
            foreach ($forward as $e) {
                if ($col[$e['to']] < $col[$e['from']] + 1) {
                    $col[$e['to']] = $col[$e['from']] + 1;
                    $changed = true;
                }
            }
            if (! $changed) {
                break;
            }
        }

        return $col;
    }

    /** @param  array<string, list<string>>  $next */
    private function reaches(array $next, string $from, string $to): bool
    {
        $seen = [];
        $stack = [$from];
        while ($stack) {
            $k = array_pop($stack);
            if ($k === $to) {
                return true;
            }
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            array_push($stack, ...($next[$k] ?? []));
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $e
     * @param  array<string, array{cx: float|int, cy: float|int, lane: int, col: int}>  $pos
     */
    private function edge(array $e, array $pos): string
    {
        $type = $e['type'] ?? 'normal';
        $a = $pos[$e['from']];
        $b = $pos[$e['to']];
        $halfW = self::NODE_W / 2;
        $halfH = self::NODE_H / 2;

        if ($b['col'] > $a['col']) {
            $x1 = $a['cx'] + $halfW;
            $x2 = $b['cx'] - $halfW;
            $xm = $b['col'] - $a['col'] === 1 ? ($x1 + $x2) / 2 : $x2 - 16;
            $d = $a['cy'] === $b['cy'] ? "M$x1 {$a['cy']} H$x2" : "M$x1 {$a['cy']} H$xm V{$b['cy']} H$x2";
            $lx = $a['cy'] === $b['cy'] ? ($x1 + $x2) / 2 : $xm + 6;
            $ly = $a['cy'] === $b['cy'] ? $a['cy'] - 7 : ($a['cy'] + $b['cy']) / 2;
            $anchor = $a['cy'] === $b['cy'] ? 'middle' : 'start';
        } elseif ($a['cy'] !== $b['cy']) {
            // Same column (or backwards): go vertically between the two cards.
            $down = $b['cy'] > $a['cy'];
            $y1 = $a['cy'] + ($down ? $halfH : -$halfH);
            $y2 = $b['cy'] + ($down ? -$halfH : $halfH);
            $x = $a['cx'] + ($b['col'] === $a['col'] ? 0 : 0);
            $d = "M$x $y1 V$y2";
            if ($b['col'] !== $a['col']) {
                $d = "M$x $y1 V".($y1 + ($down ? 20 : -20))." H{$b['cx']} V$y2";
            } else {
                $d = "M$x $y1 V$y2";
            }
            $lx = $x + 8;
            $ly = ($y1 + $y2) / 2;
            $anchor = 'start';
        } else {
            // Same lane, going backwards (or sideways): loop underneath the cards.
            $y1 = $a['cy'] + $halfH;
            $yb = $y1 + 20;
            $d = "M{$a['cx']} $y1 V$yb H{$b['cx']} V".($b['cy'] + $halfH);
            $lx = ($a['cx'] + $b['cx']) / 2;
            $ly = $yb - 4;
            $anchor = 'middle';
        }

        $label = isset($e['label']) && $e['label'] !== '' ? sprintf('<text x="%s" y="%s" text-anchor="%s" class="edge-label %s">%s</text>', $lx, $ly, $anchor, $type, e($this->clip($e['label'], 30))) : '';

        return sprintf('<path d="%s" class="edge %s" marker-end="url(#arrow-%s)"/>%s', $d, $type, $type, $label);
    }

    /**
     * @param  array<string, mixed>  $s
     * @param  array{cx: float|int, cy: float|int, lane: int, col: int}  $p
     */
    private function node(string $key, array $s, array $p, bool $problem): string
    {
        $type = $s['type'];
        $x = $p['cx'] - self::NODE_W / 2;
        $y = $p['cy'] - self::NODE_H / 2;
        $attrs = sprintf('class="node node-%s%s" data-step="%s" tabindex="0" role="button" aria-label="%s"', $type, $problem ? ' problem' : '', e($key), e($s['title']));
        $lines = $this->wrap($s['title'], $type === 'decision' ? 18 : 24, 3);

        $text = '';
        $lineH = 15;
        $startY = $p['cy'] - (count($lines) - 1) * $lineH / 2 + 4.5;
        foreach ($lines as $i => $line) {
            $text .= sprintf('<text x="%s" y="%s" text-anchor="middle" class="node-text">%s</text>', $p['cx'], $startY + $i * $lineH, e($line));
        }

        if ($type === 'decision') {
            $w = self::NODE_W;
            $h = self::NODE_H + 18;
            $pts = sprintf('%s,%s %s,%s %s,%s %s,%s', $p['cx'], $p['cy'] - $h / 2, $p['cx'] + $w / 2, $p['cy'], $p['cx'], $p['cy'] + $h / 2, $p['cx'] - $w / 2, $p['cy']);

            return "<g $attrs><polygon points=\"$pts\" class=\"shape\"/>$text</g>";
        }

        $rx = in_array($type, ['start', 'end'], true) ? self::NODE_H / 2 : 10;
        $shape = sprintf('<rect x="%s" y="%s" width="%d" height="%d" rx="%d" class="shape"/>', $x, $y, self::NODE_W, self::NODE_H, $rx);
        $badge = '';
        if ($type === 'step' && ! empty($s['number'])) {
            $badge = sprintf('<circle cx="%s" cy="%s" r="11" class="badge"/><text x="%s" y="%s" text-anchor="middle" class="badge-text">%s</text>', $x + 2, $y + 2, $x + 2, $y + 6, e($s['number']));
        }
        $deadline = ! empty($s['deadline']) ? sprintf('<text x="%s" y="%s" text-anchor="middle" class="deadline">⏱ %s</text>', $p['cx'], $y + self::NODE_H - 6, e($this->clip($s['deadline'], 26))) : '';

        return "<g $attrs>$shape$badge$text$deadline</g>";
    }

    /** @return list<string> */
    private function wrap(string $text, int $width, int $maxLines): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/', trim($text)) as $word) {
            if ($line !== '' && mb_strlen($line.' '.$word) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = trim($line.' '.$word);
            }
        }
        $lines[] = $line;

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = $this->clip($lines[$maxLines - 1], $width - 1).'…';
        }

        return $lines;
    }

    private function clip(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)).'…' : $text;
    }

    private function svg(int|float $w, int|float $h, string $body): string
    {
        $css = <<<'CSS'
.lane{fill:#fff}.lane1{fill:#f8f9fb}.lane-label-bg{fill:#eef0f4;stroke:#d9dde4}
.lane-label{font:600 12px Outfit,Arial,sans-serif;fill:#3b4350}
.muted{font:14px Outfit,Arial,sans-serif;fill:#6b7480}
.node{cursor:pointer;outline:none}
.node .shape{fill:#fff;stroke:#cfd5de;stroke-width:1.5}
.node:hover .shape,.node:focus .shape,.node.selected .shape{stroke:#c8102e;stroke-width:2.5}
.node-decision .shape{fill:#dcebfa;stroke:#7fb2e5}
.node.problem .shape{fill:#fde7ea;stroke:#e88a95}
.node-start .shape{fill:#e3f5ea;stroke:#7cc59a}.node-end .shape{fill:#2d3440;stroke:#2d3440}
.node-end .node-text{fill:#fff}
.node-text{font:500 12.5px Outfit,Arial,sans-serif;fill:#1f2630;pointer-events:none}
.badge{fill:#c8102e}.badge-text{font:700 12px Outfit,Arial,sans-serif;fill:#fff;pointer-events:none}
.deadline{font:500 10.5px Outfit,Arial,sans-serif;fill:#8a4b00;pointer-events:none}
.edge{fill:none;stroke:#59616f;stroke-width:1.6}
.edge.problem{stroke:#d0312d}.edge.delayed{stroke:#7b8494;stroke-dasharray:6 4}
.edge-label{font:500 11px Outfit,Arial,sans-serif;fill:#3b4350;paint-order:stroke;stroke:#fff;stroke-width:4px;stroke-linejoin:round}
.edge-label.problem{fill:#b3201c}
CSS;
        $marker = fn (string $t, string $c) => sprintf('<marker id="arrow-%s" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="8" markerHeight="8" orient="auto-start-reverse"><path d="M0 0L10 5L0 10z" fill="%s"/></marker>', $t, $c);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s" width="%s" height="%s" role="img" aria-label="Diagram alur SOP"><style>%s</style><defs>%s%s%s</defs>%s</svg>',
            $w, $h, $w, $h, $css, $marker('normal', '#59616f'), $marker('problem', '#d0312d'), $marker('delayed', '#7b8494'), $body,
        );
    }
}
