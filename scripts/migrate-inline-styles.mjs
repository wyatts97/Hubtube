#!/usr/bin/env node
/**
 * One-shot transform for Phase 1E.
 *
 * Replaces common `style="color: var(--color-…)"` / `style="background-color: …"`
 * / `style="border-…: 1px solid var(--color-border)"` patterns with Tailwind
 * v4 utility classes that map to the same @theme tokens defined in
 * resources/css/app.css.
 *
 * IMPORTANT: This script NEVER touches `:style` (dynamic bindings) or
 * conditional ternary style expressions — only literal `style="..."` attrs.
 *
 * Scope: resources/js/**\/*.vue  (Pages, Components, Layouts)
 *
 * Usage:
 *   node scripts/migrate-inline-styles.mjs            # dry-run (prints diff counts)
 *   node scripts/migrate-inline-styles.mjs --apply    # writes changes
 */

import { readFileSync, writeFileSync, readdirSync, statSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const APPLY = process.argv.includes('--apply');
const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

function listVueFiles() {
    const out = [];
    const walk = (d) => {
        for (const e of readdirSync(d)) {
            const p = path.join(d, e);
            const s = statSync(p);
            if (s.isDirectory()) walk(p);
            else if (p.endsWith('.vue')) out.push(p);
        }
    };
    walk(path.join(ROOT, 'resources/js'));
    return out;
}

// --- Tailwind class mapping (matches @theme tokens in app.css) ---
// Token naming: bg-bg-primary, text-text-primary, border-border, etc.
const CLASS_MAP = {
    // color:
    'color: var(--color-text-primary)':   'text-text-primary',
    'color: var(--color-text-secondary)': 'text-text-secondary',
    'color: var(--color-text-muted)':     'text-text-muted',
    'color: var(--color-accent)':         'text-accent',
    'color: #fff':                        'text-white',
    'color: #ffffff':                     'text-white',

    // background-color:
    'background-color: var(--color-bg-primary)':   'bg-bg-primary',
    'background-color: var(--color-bg-secondary)': 'bg-bg-secondary',
    'background-color: var(--color-bg-card)':      'bg-bg-card',
    'background-color: var(--color-bg-hover)':     'bg-bg-hover',
    'background-color: var(--color-hover)':        'bg-bg-hover',
    'background-color: var(--color-input-bg)':     'bg-bg-input',
    'background-color: var(--color-accent)':       'bg-accent',

    // borders (literal strings only)
    'border: 1px solid var(--color-border)':        'border border-border',
    'border-top: 1px solid var(--color-border)':    'border-t border-border',
    'border-bottom: 1px solid var(--color-border)': 'border-b border-border',
    'border-left: 1px solid var(--color-border)':   'border-l border-border',
    'border-right: 1px solid var(--color-border)':  'border-r border-border',

    // border-color standalone (used with border-t/b already in class list)
    'border-color: var(--color-border)': 'border-border',
};

// Regex matches a full `style="..."` attribute and captures its body.
// Avoids :style by requiring the attribute start with `style=`, not `:style=`
// (lookbehind needs a preceding char that is not `:`).
const STYLE_ATTR_RE = /(?<![:@])\bstyle="([^"]*)"/g;

// Split declarations on `;`, trim, drop empties.
function parseDecls(body) {
    return body
        .split(';')
        .map((s) => s.trim())
        .filter(Boolean);
}

// Normalize whitespace inside a declaration: "color:  var(--x)" -> "color: var(--x)"
function normalizeDecl(d) {
    return d.replace(/\s+/g, ' ').trim();
}

/**
 * Given an existing `class="..."` attribute body (or undefined) and an array
 * of Tailwind classes to add, return the merged class list (deduped).
 */
function mergeClasses(existing, additions) {
    const set = new Set(
        (existing || '')
            .split(/\s+/)
            .map((c) => c.trim())
            .filter(Boolean)
    );
    for (const c of additions) {
        for (const piece of c.split(/\s+/)) set.add(piece);
    }
    return [...set].join(' ');
}

// --- Main transform per file ---
function transformFile(source) {
    let result = source;
    let replacements = 0;
    let partialLeftovers = 0;

    // We need to process each tag in reverse order of style="..." occurrence
    // so that inserting/modifying `class="..."` doesn't shift indices.
    const hits = [];
    let m;
    while ((m = STYLE_ATTR_RE.exec(result))) {
        hits.push({ index: m.index, length: m[0].length, body: m[1] });
    }
    STYLE_ATTR_RE.lastIndex = 0;

    // Walk hits in reverse.
    for (let i = hits.length - 1; i >= 0; i--) {
        const h = hits[i];
        const decls = parseDecls(h.body).map(normalizeDecl);
        const translated = [];
        const leftover = [];

        for (const d of decls) {
            const cls = CLASS_MAP[d];
            if (cls) translated.push(cls);
            else leftover.push(d);
        }

        if (translated.length === 0) continue; // nothing to do

        // Find the enclosing tag start to locate a sibling `class=` attribute.
        // We look backward from h.index to find '<tagname' then forward to the
        // matching '>' (ignoring '>' inside attribute values is non-trivial but
        // style="..." is already consumed, so finding the next unquoted '>'
        // after h.index+h.length is reliable enough for Vue templates).
        const tagStart = (() => {
            // Scan back for '<' that isn't within a quoted attribute. Cheap
            // heuristic: last '<' before h.index.
            return result.lastIndexOf('<', h.index);
        })();
        if (tagStart < 0) continue;

        const tagEnd = (() => {
            // Find the next '>' after h.index+h.length, skipping quoted sections.
            let i = h.index + h.length;
            let inQuote = null;
            while (i < result.length) {
                const c = result[i];
                if (inQuote) {
                    if (c === inQuote) inQuote = null;
                } else if (c === '"' || c === "'") {
                    inQuote = c;
                } else if (c === '>') {
                    return i;
                }
                i++;
            }
            return -1;
        })();
        if (tagEnd < 0) continue;

        const tagBody = result.slice(tagStart, tagEnd + 1);
        const classMatch = tagBody.match(/(?<![:@])\bclass="([^"]*)"/);

        let newTagBody;
        if (classMatch) {
            const merged = mergeClasses(classMatch[1], translated);
            newTagBody = tagBody.replace(
                classMatch[0],
                `class="${merged}"`
            );
        } else {
            // Insert class="..." right before style=
            const merged = mergeClasses('', translated);
            const styleAttr = `style="${h.body}"`;
            newTagBody = tagBody.replace(
                styleAttr,
                `class="${merged}" ${styleAttr}`
            );
        }

        // Now remove/rewrite style attr in newTagBody. We must NOT touch
        // `:style="..."` (dynamic binding) or `@style` — the lookbehind
        // excludes both.
        if (leftover.length === 0) {
            newTagBody = newTagBody.replace(/\s*(?<![:@])\bstyle="[^"]*"/, '');
        } else {
            const newBody = leftover.join('; ');
            newTagBody = newTagBody.replace(
                /(?<![:@])\bstyle="[^"]*"/,
                `style="${newBody};"`
            );
            partialLeftovers++;
        }

        // Replace original tag in result
        result = result.slice(0, tagStart) + newTagBody + result.slice(tagEnd + 1);
        replacements++;
    }

    return { result, replacements, partialLeftovers };
}

// --- Run ---
const files = listVueFiles();
let totalFiles = 0;
let totalReps = 0;
let totalPartial = 0;
let changedFiles = 0;

for (const file of files) {
    const src = readFileSync(file, 'utf8');
    const { result, replacements, partialLeftovers } = transformFile(src);
    totalFiles++;
    totalReps += replacements;
    totalPartial += partialLeftovers;

    if (replacements > 0 && result !== src) {
        changedFiles++;
        if (APPLY) writeFileSync(file, result, 'utf8');
        const rel = path.relative(ROOT, file);
        console.log(
            `${APPLY ? '✔' : '·'} ${rel}  — ${replacements} style attr${replacements === 1 ? '' : 's'} migrated${partialLeftovers ? ` (${partialLeftovers} partial)` : ''}`
        );
    }
}

console.log('');
console.log(`Files scanned:         ${totalFiles}`);
console.log(`Files changed:         ${changedFiles}`);
console.log(`Style attrs migrated:  ${totalReps}`);
console.log(`Partially migrated:    ${totalPartial}  (leftover decl kept in style=)`);
console.log(APPLY ? '\nApplied.' : '\nDry run. Re-run with --apply to write changes.');
