import { useEffect, useRef } from 'react'

function GoldDustBackground() {
  const canvasRef = useRef(null)

  useEffect(() => {
    const canvas = canvasRef.current
    if (!canvas) return

    const ctx = canvas.getContext('2d', { alpha: true })

    let width = 0
    let height = 0
    let dpr = 1
    let particles = []
    let sprites = []
    let animationFrame = null

    let scrollTarget = 0
    let scrollProgress = 0

    const mouse = {
      x: -9999,
      y: -9999,
      active: false,
      lastMove: 0,
    }

    const random = (min, max) => min + Math.random() * (max - min)

    const createSprite = (w, h, draw) => {
      const sprite = document.createElement('canvas')
      sprite.width = w
      sprite.height = h

      const spriteCtx = sprite.getContext('2d')
      draw(spriteCtx, w, h)

      return sprite
    }

    const buildSprites = () => {
      sprites = []

      // Μικρός ακανόνιστος κόκκος
      sprites.push(
        createSprite(8, 5, (g) => {
          g.fillStyle = 'rgba(218, 172, 92, 0.9)'
          g.fillRect(1, 2, 3, 1)
          g.fillRect(2, 1, 2, 1)
          g.fillRect(4, 2, 1, 1)

          g.fillStyle = 'rgba(245, 225, 181, 0.7)'
          g.fillRect(1, 1, 1, 1)
        }),
      )

      // Λεπτός χρυσός κόκκος
      sprites.push(
        createSprite(10, 4, (g, w) => {
          const gradient = g.createLinearGradient(0, 0, w, 0)

          gradient.addColorStop(0, 'rgba(194,138,54,0)')
          gradient.addColorStop(0.25, 'rgba(218,172,92,.7)')
          gradient.addColorStop(0.55, 'rgba(243,213,157,.95)')
          gradient.addColorStop(1, 'rgba(194,138,54,0)')

          g.fillStyle = gradient
          g.fillRect(1, 1, 8, 2)
        }),
      )

      // Μικρό cluster σκόνης
      sprites.push(
        createSprite(7, 7, (g) => {
          g.fillStyle = 'rgba(207,157,73,.8)'
          g.fillRect(2, 2, 1, 1)
          g.fillRect(3, 3, 1, 1)
          g.fillRect(4, 2, 1, 1)
          g.fillRect(2, 4, 1, 1)

          g.fillStyle = 'rgba(242,217,168,.7)'
          g.fillRect(3, 2, 1, 1)
        }),
      )

      // Πολύ μικρό shimmer
      sprites.push(
        createSprite(12, 6, (g, w) => {
          const gradient = g.createLinearGradient(0, 0, w, 0)

          gradient.addColorStop(0, 'rgba(245,227,188,0)')
          gradient.addColorStop(0.4, 'rgba(245,227,188,.75)')
          gradient.addColorStop(0.6, 'rgba(255,244,220,.92)')
          gradient.addColorStop(1, 'rgba(245,227,188,0)')

          g.fillStyle = gradient
          g.fillRect(1, 2, 10, 1.5)
        }),
      )
    }

    const waveY = (xNorm, phase) => {
      const center = height * 0.53

      return (
        center +
        Math.sin(xNorm * Math.PI * 2.15 + phase) * height * 0.05 +
        Math.sin(xNorm * Math.PI * 5.1 - phase * 0.42) * height * 0.015
      )
    }

    const waveSlope = (xNorm, phase) => {
      const epsilon = 0.0015

      const y1 = waveY(Math.max(0, xNorm - epsilon), phase)
      const y2 = waveY(Math.min(1, xNorm + epsilon), phase)

      return (y2 - y1) / (epsilon * width * 2)
    }

    const buildParticles = () => {
      particles = []

      let count = 3600

      if (width < 768) {
        count = 1200
      } else if (width < 1200) {
        count = 2200
      }

      for (let i = 0; i < count; i += 1) {
        const xNorm = Math.random()

        let offset

        if (Math.random() < 0.75) {
          offset =
            (Math.random() + Math.random() + Math.random() - 1.5) *
            height *
            0.045
        } else {
          offset = random(-height * 0.1, height * 0.1)
        }

        const typeRandom = Math.random()

        let type = 0

        if (typeRandom > 0.58) type = 1
        if (typeRandom > 0.8) type = 2
        if (typeRandom > 0.94) type = 3

        particles.push({
          xNorm,
          offset,

          x: xNorm * width,
          y: height * 0.5,

          vx: 0,
          vy: 0,

          scale: random(0.42, 1.05),
          alpha: random(0.04, 0.18),

          twinkle: random(0.7, 1.8),
          seed: random(0, Math.PI * 2),

          type,

          hold: 0,
          holdX: 0,
          holdY: 0,
          holdStrength: 0,
        })
      }
    }

    const resize = () => {
      width = window.innerWidth
      height = window.innerHeight

      dpr = Math.min(window.devicePixelRatio || 1, 1.5)

      canvas.width = Math.round(width * dpr)
      canvas.height = Math.round(height * dpr)

      canvas.style.width = `${width}px`
      canvas.style.height = `${height}px`

      ctx.setTransform(dpr, 0, 0, dpr, 0, 0)

      buildSprites()
      buildParticles()
    }

    const handlePointerMove = (event) => {
      mouse.x = event.clientX
      mouse.y = event.clientY
      mouse.active = true
      mouse.lastMove = performance.now()
    }

    const handlePointerLeave = () => {
      mouse.active = false
    }

    const handleScroll = () => {
      const maxScroll = Math.max(
        1,
        document.documentElement.scrollHeight - window.innerHeight,
      )

      scrollTarget = window.scrollY / maxScroll
    }

    const drawParticle = (particle, angle, alpha, hoverBoost) => {
      const sprite = sprites[particle.type]

      if (!sprite) return

      const scale = particle.scale * (1 + hoverBoost * 0.8)

      const w = sprite.width * scale
      const h = sprite.height * scale

      ctx.save()

      ctx.translate(particle.x, particle.y)
      ctx.rotate(angle + Math.sin(particle.seed * 8) * 0.12)

      ctx.globalAlpha = alpha

      if (particle.type === 3 || hoverBoost > 0.08) {
        ctx.shadowColor = 'rgba(222,177,96,.35)'
        ctx.shadowBlur = 5 + hoverBoost * 8
      }

      ctx.drawImage(sprite, -w / 2, -h / 2, w, h)

      ctx.restore()
    }

    const animate = (time) => {
      if (document.hidden) {
        animationFrame = requestAnimationFrame(animate)
        return
      }

      scrollProgress += (scrollTarget - scrollProgress) * 0.035

      const phase =
        time * 0.00012 +
        scrollProgress * Math.PI * 2.7

      ctx.clearRect(0, 0, width, height)

      const cursorActive =
        mouse.active &&
        performance.now() - mouse.lastMove < 1300

      particles.forEach((particle) => {
        const targetX = particle.xNorm * width

        const baseY = waveY(particle.xNorm, phase)
        const slope = waveSlope(particle.xNorm, phase)

        let normalX = -slope
        let normalY = 1

        const normalLength =
          Math.hypot(normalX, normalY) || 1

        normalX /= normalLength
        normalY /= normalLength

        const scrollShift =
          Math.sin(
            particle.xNorm * 6 +
              scrollProgress * 7.5 +
              particle.seed,
          ) *
          15 *
          scrollProgress

        const breathe =
          Math.sin(
            time * 0.00055 * particle.twinkle +
              particle.seed,
          ) * 1.5

        let targetParticleX =
          targetX +
          normalX * particle.offset +
          scrollShift

        let targetParticleY =
          baseY +
          normalY * particle.offset +
          breathe

        const dx = mouse.x - particle.x
        const dy = mouse.y - particle.y
        const distance = Math.hypot(dx, dy)

        let hoverBoost = 0

        if (cursorActive && distance < 165) {
          const influence = 1 - distance / 165
          const magnetic = influence * influence

          targetParticleX += dx * 0.23 * magnetic
          targetParticleY += dy * 0.23 * magnetic

          hoverBoost = influence * 0.26

          if (
            distance < 78 &&
            Math.random() < 0.025 + influence * 0.045
          ) {
            particle.hold = random(9, 26)

            particle.holdX =
              mouse.x + random(-25, 25)

            particle.holdY =
              mouse.y + random(-18, 18)

            particle.holdStrength =
              random(0.42, 0.76)
          }
        }

        if (particle.hold > 0) {
          particle.hold -= 1

          targetParticleX =
            targetParticleX *
              (1 - particle.holdStrength) +
            particle.holdX * particle.holdStrength

          targetParticleY =
            targetParticleY *
              (1 - particle.holdStrength) +
            particle.holdY * particle.holdStrength

          particle.holdStrength *= 0.975
        }

        particle.vx +=
          (targetParticleX - particle.x) * 0.018

        particle.vy +=
          (targetParticleY - particle.y) * 0.018

        particle.vx *= 0.89
        particle.vy *= 0.89

        particle.x += particle.vx
        particle.y += particle.vy

        const shimmer =
          0.82 +
          Math.sin(
            time * 0.0012 * particle.twinkle +
              particle.seed,
          ) *
            0.18

        const alpha = Math.min(
          0.52,
          particle.alpha * shimmer + hoverBoost,
        )

        const tangentAngle = Math.atan2(slope, 1)

        drawParticle(
          particle,
          tangentAngle,
          alpha,
          hoverBoost,
        )
      })

      animationFrame = requestAnimationFrame(animate)
    }

    resize()
    handleScroll()

    window.addEventListener('resize', resize)
    window.addEventListener('scroll', handleScroll, {
      passive: true,
    })

    window.addEventListener(
      'pointermove',
      handlePointerMove,
      { passive: true },
    )

    document.addEventListener(
      'mouseleave',
      handlePointerLeave,
    )

    animationFrame = requestAnimationFrame(animate)

    return () => {
      cancelAnimationFrame(animationFrame)

      window.removeEventListener('resize', resize)
      window.removeEventListener('scroll', handleScroll)
      window.removeEventListener(
        'pointermove',
        handlePointerMove,
      )

      document.removeEventListener(
        'mouseleave',
        handlePointerLeave,
      )
    }
  }, [])

  return (
    <canvas
      ref={canvasRef}
      aria-hidden="true"
      style={{
        position: 'fixed',
        inset: 0,
        width: '100%',
        height: '100%',
        pointerEvents: 'none',
        zIndex: 0,
      }}
    />
  )
}

export default GoldDustBackground
