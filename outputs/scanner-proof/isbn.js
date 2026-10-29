(function (root) {
    'use strict';
    function canonicalIsbn(input) {
        const value = String(input).replace(/[\s-]/g, '').toUpperCase();
        if (/^\d{9}[\dX]$/.test(value)) {
            const sum = [...value].reduce((total, digit, i) => total + (digit === 'X' ? 10 : Number(digit)) * (10 - i), 0);
            if (sum % 11 !== 0) return null;
            const base = '978' + value.slice(0, 9);
            const check = (10 - [...base].reduce((total, digit, i) => total + Number(digit) * (i % 2 ? 3 : 1), 0) % 10) % 10;
            return base + check;
        }
        if (!/^97[89]\d{10}$/.test(value)) return null;
        const sum = [...value].reduce((total, digit, i) => total + Number(digit) * (i % 2 ? 3 : 1), 0);
        return sum % 10 === 0 ? value : null;
    }
    root.WiseOwlIsbn = { canonicalIsbn };
    if (typeof module !== 'undefined' && module.exports) module.exports = { canonicalIsbn };
})(globalThis);
