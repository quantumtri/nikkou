import { defineConfig } from 'astro/config';

// Set SITE_URL (e.g. https://nikkou.id) at build time to enable canonical URLs and absolute OG images.
export default defineConfig({
  site: process.env.SITE_URL || undefined,
  build: { inlineStylesheets: 'auto' },
});
