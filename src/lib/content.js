import cfg from '../../site.config.mjs';
import { getT } from '../i18n/index.js';

export const preview = process.env.PREVIEW_PLACEHOLDERS === '1';

/** In preview builds show "[label]" for missing data, in production return null so the section hides. */
export const ph = (label) => (preview ? `[${label}]` : null);
export const pick = (value, label) => value ?? ph(label);

const number = String(cfg.WA_NUMBER || '').replace(/\D/g, '');
if (number && !/^62\d{8,13}$/.test(number)) {
  console.warn(`[site.config] WA_NUMBER "${cfg.WA_NUMBER}" is not in 628xxx format.`);
}
if (!number && !preview) {
  console.warn('[site.config] WA_NUMBER is empty: WhatsApp links fall back to a generic wa.me link. Fill it before release.');
}

export const waNumber = number;
export const waLink = `https://wa.me/${number}?text=${encodeURIComponent(cfg.WA_TEXT)}`;
export const waLabel = number ? `Chat WhatsApp +${number}` : preview ? 'Chat WhatsApp [nomor]' : 'Chat WhatsApp';

/** Locale-aware WhatsApp link and label. Indonesian keeps WA_TEXT from site.config.mjs. */
export const waLinkFor = (lang) => {
  const text = lang === 'id' ? cfg.WA_TEXT : getT(lang).common.wa.text;
  return `https://wa.me/${number}?text=${encodeURIComponent(text)}`;
};
export const waLabelFor = (lang) => {
  const w = getT(lang).common.wa;
  return number ? w.label(number) : preview ? w.labelPlaceholder : w.labelNoNumber;
};

export const formEndpoint = cfg.FORM_ENDPOINT || null;
export const waBase = `https://wa.me/${number}`;

export const brand = cfg.brand;
export { cfg };
