#!/usr/bin/env node
/**
 * CLAUDE.md と AGENTS.md の共通プリアンブルが一致していることを検査する。
 *
 * 背景: 監査（docs/audit/2026-07-26-pre-release-audit.md）時点で、
 * AGENTS.md には「プロダクト仕様の正は docs/」という本プロジェクト最重要の
 * 優先順位が欠落していた。CLAUDE.md との差分は9行だけで、その9行が
 * まるごと規約だった。片方だけ更新される事故は人間の注意では防げないため、
 * CI で機械的に検査する。
 *
 * 正は .cursor/rules/*.mdc 側にあり、この2ファイルはそこへの参照を持つだけ。
 */

import { readFileSync } from 'node:fs';

const START = '<!-- AGENT-PREAMBLE:START -->';
const END = '<!-- AGENT-PREAMBLE:END -->';
const FILES = ['CLAUDE.md', 'AGENTS.md'];

/**
 * @param {string} file
 * @returns {string}
 */
function extractPreamble(file) {
    let content;
    try {
        content = readFileSync(file, 'utf8');
    } catch {
        fail(`${file} を読み込めません。`);
    }

    const start = content.indexOf(START);
    const end = content.indexOf(END);

    if (start === -1 || end === -1) {
        fail(
            `${file} に プリアンブルマーカーがありません。\n` +
                `  ${START} と ${END} で囲まれた節が必要です。`,
        );
    }

    if (end < start) {
        fail(`${file} のマーカー順序が逆です（END が START より前にあります）。`);
    }

    return content.slice(start + START.length, end).trim();
}

/**
 * @param {string} message
 * @returns {never}
 */
function fail(message) {
    console.error(`\n✗ ${message}\n`);
    process.exit(1);
}

const [claude, agents] = FILES.map(extractPreamble);

if (claude !== agents) {
    const claudeLines = claude.split('\n');
    const agentsLines = agents.split('\n');
    const max = Math.max(claudeLines.length, agentsLines.length);

    let firstDiff = -1;
    for (let i = 0; i < max; i++) {
        if (claudeLines[i] !== agentsLines[i]) {
            firstDiff = i;
            break;
        }
    }

    fail(
        `CLAUDE.md と AGENTS.md のプリアンブルが一致しません（最初の差分: ${firstDiff + 1} 行目）。\n\n` +
            `  CLAUDE.md: ${JSON.stringify(claudeLines[firstDiff] ?? '(行なし)')}\n` +
            `  AGENTS.md: ${JSON.stringify(agentsLines[firstDiff] ?? '(行なし)')}\n\n` +
            `  両ファイルの ${START} 〜 ${END} を同一内容にしてください。\n` +
            `  ルール本文の正は .cursor/rules/*.mdc です。`,
    );
}

console.log(`✓ CLAUDE.md / AGENTS.md のプリアンブルは一致しています（${claude.split('\n').length} 行）。`);
