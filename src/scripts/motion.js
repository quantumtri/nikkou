import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import SplitType from 'split-type';
import { useLenis } from './useLenis.js';

gsap.registerPlugin(ScrollTrigger);

const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function ready() {
  window.__motionReady = true;
  document.documentElement.classList.add('motion-ready');
}

if (reduce) {
  document.documentElement.classList.remove('motion');
} else {
  useLenis();

  const splits = [];
  const ease = 'power3.out';

  // Navbar drops in
  gsap.from('.header', { yPercent: -100, opacity: 0, duration: 0.9, ease, clearProps: 'all' });

  // Hero intro
  const heroTl = gsap.timeline({ defaults: { ease }, delay: 0.15 });
  const h1 = document.querySelector('.hero h1');
  if (h1) {
    const s = new SplitType(h1, { types: 'lines,words' });
    splits.push(s);
    heroTl.from(s.words, { yPercent: 110, duration: 1, stagger: 0.07 }, 0.1);
  }
  heroTl
    .from('.hero-sub', { y: 24, opacity: 0, duration: 0.9 }, 0.5)
    .from('.hero-copy .btn', { y: 20, opacity: 0, duration: 0.8, stagger: 0.1 }, 0.65)
    .from('.hero-grid .fig-item', { y: 70, opacity: 0, scale: 0.94, duration: 1.1, stagger: 0.12 }, 0.6);

  // Section headings: lines rise from a mask
  document.querySelectorAll('.h2, .cta h2').forEach((el) => {
    const s = new SplitType(el, { types: 'lines,words' });
    splits.push(s);
    gsap.from(s.words, {
      yPercent: 110,
      duration: 0.9,
      ease,
      stagger: 0.05,
      scrollTrigger: { trigger: el, start: 'top 88%', once: true },
    });
  });

  // Scrubbed text: words light up as you scroll
  document.querySelectorAll('#masalah .lead, .cta p').forEach((el) => {
    const s = new SplitType(el, { types: 'words' });
    splits.push(s);
    gsap.fromTo(
      s.words,
      { opacity: 0.18 },
      {
        opacity: 1,
        ease: 'none',
        stagger: 0.1,
        scrollTrigger: { trigger: el, start: 'top 85%', end: 'bottom 55%', scrub: true },
      }
    );
  });

  // Fade-up for cards and blocks, batched so neighbours stagger
  const fadeTargets = '.cards > *, .step, .pcard, .acard, .faq-wrap details, .scroll-x, .quote,.expert .wrap > *, .consult-copy > *, .chip';
  ScrollTrigger.batch(fadeTargets, {
    start: 'top 90%',
    once: true,
    onEnter: (batch) =>
      gsap.fromTo(
        batch,
        { y: 48, opacity: 0 },
        { y: 0, opacity: 1, duration: 0.9, ease, stagger: 0.12, overwrite: true, clearProps: 'transform,opacity' }
      ),
  });
  gsap.set(fadeTargets, { opacity: 0, y: 48 });

  // Parallax on cutout photos and step images
  document.querySelectorAll('.fig img, .step-img img, .expert img').forEach((img) => {
    const depth = img.closest('.hero') ? 28 : 40;
    gsap.fromTo(
      img,
      { y: depth },
      {
        y: -depth,
        ease: 'none',
        scrollTrigger: { trigger: img.closest('.fig, .step-img, .expert') || img, start: 'top bottom', end: 'bottom top', scrub: true },
      }
    );
  });
  document.querySelectorAll('.fig .shape').forEach((shape) => {
    gsap.to(shape, {
      y: -18,
      ease: 'none',
      scrollTrigger: { trigger: shape.parentElement, start: 'top bottom', end: 'bottom top', scrub: true },
    });
  });

  // Re-split headings on width change so line breaks stay correct
  let lastW = window.innerWidth;
  let t;
  window.addEventListener('resize', () => {
    clearTimeout(t);
    t = setTimeout(() => {
      if (window.innerWidth === lastW) return;
      lastW = window.innerWidth;
      splits.forEach((s) => s.split({ types: s.settings.types }));
      ScrollTrigger.refresh();
    }, 250);
  });
  window.addEventListener('load', () => ScrollTrigger.refresh());
}

ready();
