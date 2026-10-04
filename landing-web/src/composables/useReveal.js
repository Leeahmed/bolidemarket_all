import { onBeforeUnmount, onMounted, nextTick } from 'vue'

export function useReveal(root, hero = false) {
  let media
  let active = true
  onMounted(async () => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
    const [{ gsap }, { ScrollTrigger }] = await Promise.all([import('gsap'), import('gsap/ScrollTrigger')])
    if (!active) return
    await nextTick()
    gsap.registerPlugin(ScrollTrigger)
    media = gsap.matchMedia()
    media.add('(prefers-reduced-motion: no-preference)', () => {
      const elements = [...(root.value?.matches('[data-reveal]') ? [root.value] : []), ...(root.value?.querySelectorAll('[data-reveal]') || [])]
      elements.forEach((element, index) => {
        gsap.from(element, { opacity: 0, y: hero ? 16 : 12, duration: hero ? 0.6 : 0.45,
          delay: hero ? index * 0.09 : 0, clearProps: 'transform,opacity',
          ...(hero ? {} : { scrollTrigger: { trigger: element, start: 'top 94%', once: true } }),
        })
      })
      root.value?.querySelectorAll('[data-parallax]').forEach((element) => {
        gsap.fromTo(element, { y: -6 }, { y: 6, ease: 'none', scrollTrigger: { trigger: element, start: 'top bottom', end: 'bottom top', scrub: 1 } })
      })
    }, root.value)
  })
  onBeforeUnmount(() => { active = false; media?.revert() })
}
