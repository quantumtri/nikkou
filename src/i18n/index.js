import id from './id.js';
import en from './en.js';
import zh from './zh.js';
import ja from './ja.js';
import ko from './ko.js';

export const locales = ['id', 'en', 'zh', 'ja', 'ko'];
export const defaultLocale = 'id';
const dicts = { id, en, zh, ja, ko };

export const getT = (lang) => dicts[lang] ?? dicts[defaultLocale];
export const isLocale = (v) => locales.includes(v);

/** Strings may contain {it}emphasis{/it}; this turns it into the italic accent span. */
export const rich = (s) => String(s).replace(/\{it\}/g, '<span class="it">').replace(/\{\/it\}/g, '</span>');

/** Prefix a site path with the locale ("/" stays "/" for Indonesian, becomes "/en/" for English). */
export const localePath = (lang, path = '/') => (lang === defaultLocale ? path : `/${lang}${path === '/' ? '/' : path}`);

/** Remove a leading locale segment: "/en/artikel/x" -> "/artikel/x". */
export const stripLocale = (pathname) => {
  const m = pathname.match(/^\/([a-z]{2})(\/.*)?$/);
  if (m && isLocale(m[1]) && m[1] !== defaultLocale) return m[2] || '/';
  return pathname;
};

/**
 * Config values may be a plain string (Indonesian) or { id, en, zh, ja, ko }.
 * Missing translations fall back to the Indonesian text.
 */
export const loc = (value, lang) => {
  if (value && typeof value === 'object' && !Array.isArray(value) && Object.keys(value).length && Object.keys(value).every(isLocale)) {
    return value[lang] ?? value[defaultLocale] ?? null;
  }
  return value;
};
