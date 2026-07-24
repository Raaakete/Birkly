#!/usr/bin/env node
'use strict';

function parseForLoopHeader(str) {
    const m = String(str || '').match(/\{for\s+(?:each\s+)?([a-zA-Z0-9_]+)\s+in\s+(.+?)\}/);
    if (!m) return null;
    let rest = m[2].trim();
    let filterExpr = null;
    let orderExpr = null;
    let limitExpr = null;
    const orderMatch = rest.match(/\s+ordered\s+by\s+(.+)$/i);
    if (orderMatch) {
        orderExpr = orderMatch[1].trim();
        const limitInOrder = orderExpr.match(/^(.+?)\s+limit\s+(\d+)\s*$/i);
        if (limitInOrder) {
            orderExpr = limitInOrder[1].trim();
            limitExpr = parseInt(limitInOrder[2], 10);
        }
        rest = rest.substring(0, orderMatch.index).trim();
    }
    const withMatch = rest.match(/\s+with\s+(.+)$/i);
    if (withMatch) {
        filterExpr = withMatch[1].trim();
        rest = rest.substring(0, withMatch.index).trim();
    }
    const limitMatch = rest.match(/\s+limit\s+(\d+)\s*$/i);
    if (limitMatch) {
        limitExpr = parseInt(limitMatch[1], 10);
        rest = rest.substring(0, limitMatch.index).trim();
    }
    return { full: m[0], itemVar: m[1], inClause: rest, filterExpr, orderExpr, limitExpr };
}

let failures = 0;
function assertTrue(name, ok) {
    console.log(name + ': ' + (ok ? 'PASS' : 'FAIL'));
    if (!ok) failures++;
}

const blogLoop = "{for each entry in 'blog' with status = published ordered by newest limit 1}";
const parsed = parseForLoopHeader(blogLoop);
assertTrue('parses blog collection', parsed && parsed.inClause === "'blog'");
assertTrue('parses status filter', parsed && parsed.filterExpr === 'status = published');
assertTrue('parses newest order', parsed && parsed.orderExpr === 'newest');
assertTrue('parses limit', parsed && parsed.limitExpr === 1);

console.log('\nfor_loop_header_test: ' + (failures === 0 ? 'PASS' : 'FAIL (' + failures + ')'));
process.exit(failures === 0 ? 0 : 1);
