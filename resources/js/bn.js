const DIGITS = '০১২৩৪৫৬৭৮৯';

export const bnDigits = (value) => String(value).replace(/[0-9]/g, (d) => DIGITS[d]);

/** Mirrors App\Support\Bangla::possessive. */
export function possessive(name) {
    name = (name || '').trim();
    if (/[A-Za-z0-9]$/.test(name)) return `${name}-এর`;
    return /[অ-ঔা-ৌৗ]$/.test(name) ? `${name}র` : `${name}ের`;
}
