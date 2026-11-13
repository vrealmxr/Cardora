/* CPU-only crop proposals. Geometry is not proof of card identity or privacy. */
/* global cv */
(function (root) {
  const distance = (a, b) => Math.hypot(a[0] - b[0], a[1] - b[1]);
  const center = points => points.reduce((s, p) => [s[0] + p[0] / 4, s[1] + p[1] / 4], [0, 0]);
  function order(points) {
    const c = center(points);
    points.sort((a, b) => Math.atan2(a[1] - c[1], a[0] - c[0]) - Math.atan2(b[1] - c[1], b[0] - c[0]));
    const first = points.reduce((best, p, i) => p[0] + p[1] < points[best][0] + points[best][1] ? i : best, 0);
    return [...points.slice(first), ...points.slice(0, first)];
  }
  function edgeSupport(points, edges) {
    return points.map((a, i) => {
      const b = points[(i + 1) % 4]; let found = 0;
      for (let j = 1; j < 80; j++) {
        const x = Math.round(a[0] + (b[0] - a[0]) * j / 80), y = Math.round(a[1] + (b[1] - a[1]) * j / 80);
        let hit = false;
        for (let dy = -2; dy <= 2 && !hit; dy++) for (let dx = -2; dx <= 2; dx++) {
          if (x + dx >= 0 && x + dx < edges.cols && y + dy >= 0 && y + dy < edges.rows && edges.data[(y + dy) * edges.cols + x + dx]) { hit = true; break; }
        }
        if (hit) found++;
      }
      return found / 79;
    });
  }
  function lineCandidates(cv, edges, frameArea) {
    const segments = new cv.Mat(), groups = [[], []];
    try {
      cv.HoughLinesP(edges, segments, 1, Math.PI / 180, 65, Math.min(edges.cols, edges.rows) * .20, 12);
      for (let i = 0; i < segments.rows; i++) {
        const [x1, y1, x2, y2] = Array.from(segments.data32S.slice(i * 4, i * 4 + 4));
        const dx = x2 - x1, dy = y2 - y1, horizontal = Math.abs(dx) > Math.abs(dy);
        const slope = horizontal ? dy / dx : dx / dy;
        if (Math.abs(slope) > .4) continue;
        const intercept = horizontal ? y1 - slope * x1 : x1 - slope * y1;
        groups[horizontal ? 0 : 1].push({ slope, intercept, length: Math.hypot(dx, dy) });
      }
      const lines = groups.map((group, index) => {
        const unique = [], mid = (index === 0 ? edges.cols : edges.rows) / 2;
        for (const line of group.sort((a, b) => b.length - a.length)) {
          if (!unique.some(other => Math.abs(line.slope - other.slope) < .04 && Math.abs(line.intercept + line.slope * mid - other.intercept - other.slope * mid) < 7)) unique.push(line);
          if (unique.length === 20) break;
        }
        return unique;
      });
      const intersect = (h, v) => {
        const x = (v.slope * h.intercept + v.intercept) / (1 - v.slope * h.slope);
        return [x, h.slope * x + h.intercept];
      };
      const candidates = [];
      for (let a = 0; a < lines[0].length; a++) for (let b = a + 1; b < lines[0].length; b++) {
        const h1 = lines[0][a], h2 = lines[0][b];
        if (Math.abs(h1.slope - h2.slope) > .20) continue;
        for (let c = 0; c < lines[1].length; c++) for (let d = c + 1; d < lines[1].length; d++) {
          const v1 = lines[1][c], v2 = lines[1][d];
          if (Math.abs(v1.slope - v2.slope) > .20) continue;
          const points = order([intersect(h1, v1), intersect(h1, v2), intersect(h2, v1), intersect(h2, v2)]);
          if (points.some(([x, y]) => x < 0 || y < 0 || x >= edges.cols || y >= edges.rows)) continue;
          const area = Math.abs(points.reduce((sum, p, i) => { const q = points[(i + 1) % 4]; return sum + p[0] * q[1] - q[0] * p[1]; }, 0)) / 2;
          if (area < frameArea * .10 || area > frameArea * .99) continue;
          const sides = points.map((p, i) => distance(p, points[(i + 1) % 4]));
          const width = (sides[0] + sides[2]) / 2, height = (sides[1] + sides[3]) / 2;
          const ratio = Math.min(width, height) / Math.max(width, height);
          if (ratio < .66 || ratio > .77 || Math.min(...sides) < 80) continue;
          if (Math.min(...edgeSupport(points, edges)) < .78) continue;
          candidates.push({ points, area, width, height, ratio, center: center(points) });
        }
      }
      return candidates;
    } finally { segments.delete(); }
  }
  function detect(cv, source) {
    const owned = [];
    const keep = mat => { owned.push(mat); return mat; };
    try {
      const scale = Math.min(1, 1000 / Math.max(source.cols, source.rows));
      const small = keep(new cv.Mat());
      cv.resize(source, small, new cv.Size(Math.round(source.cols * scale), Math.round(source.rows * scale)), 0, 0, cv.INTER_AREA);
      const gray = keep(new cv.Mat()), blur = keep(new cv.Mat()), edges = keep(new cv.Mat());
      const kernel = keep(cv.Mat.ones(3, 3, cv.CV_8U));
      const combined = keep(cv.Mat.zeros(small.rows, small.cols, cv.CV_8U));
      const channels = keep(new cv.MatVector()); cv.split(small, channels);
      const candidates = [], frameArea = small.cols * small.rows;
      // Foil colours can share luminance with the background; retain colour edges too.
      for (const channel of [-1, 0, 1, 2]) {
      if (channel < 0) cv.cvtColor(small, gray, cv.COLOR_RGBA2GRAY);
      else { const plane = channels.get(channel); plane.copyTo(gray); plane.delete(); }
      cv.GaussianBlur(gray, blur, new cv.Size(5, 5), 0);
      for (const [low, high] of [[30, 90], [60, 160], [15, 50]]) {
        cv.Canny(blur, edges, low, high);
        cv.morphologyEx(edges, edges, cv.MORPH_CLOSE, kernel);
        cv.bitwise_or(combined, edges, combined);
        const contours = new cv.MatVector(), hierarchy = new cv.Mat();
        try {
          cv.findContours(edges, contours, hierarchy, cv.RETR_LIST, cv.CHAIN_APPROX_SIMPLE);
          // Bound CPU work in cluttered photographs.
          const ranked = [];
          for (let i = 0; i < contours.size(); i++) {
            const contour = contours.get(i); const area = cv.contourArea(contour); contour.delete();
            if (area > frameArea * .08 && area < frameArea * .995) ranked.push([area, i]);
          }
          ranked.sort((a, b) => b[0] - a[0]);
          for (const [area, i] of ranked.slice(0, 60)) {
            const contour = contours.get(i), hull = new cv.Mat(), quad = new cv.Mat();
            try {
              cv.convexHull(contour, hull);
              if (area / cv.contourArea(hull) < .90) continue;
              cv.approxPolyDP(hull, quad, .02 * cv.arcLength(hull, true), true);
              if (quad.rows !== 4 || !cv.isContourConvex(quad)) continue;
              const points = order(Array.from({ length: 4 }, (_, j) => [quad.data32S[j * 2], quad.data32S[j * 2 + 1]]));
              const lengths = points.map((p, j) => distance(p, points[(j + 1) % 4]));
              if (Math.min(...lengths) < 65) continue;
              if (Math.max(lengths[0], lengths[2]) / Math.min(lengths[0], lengths[2]) > 1.5 ||
                  Math.max(lengths[1], lengths[3]) / Math.min(lengths[1], lengths[3]) > 1.5) continue;
              const width = (lengths[0] + lengths[2]) / 2, height = (lengths[1] + lengths[3]) / 2;
              const ratio = Math.min(width, height) / Math.max(width, height);
              if (ratio < .62 || ratio > .81) continue;
              const cosines = points.map((p, j) => {
                const b = points[(j + 1) % 4], c = points[(j + 2) % 4];
                return Math.abs(((b[0] - p[0]) * (c[0] - b[0]) + (b[1] - p[1]) * (c[1] - b[1])) / (lengths[j] * lengths[(j + 1) % 4]));
              });
              if (Math.max(...cosines) > .45) continue;
              // A convex hull can bridge into a stand or sleeve. Require an observed edge
              // along every side, not merely four convenient corner points.
              if (Math.min(...edgeSupport(points, edges)) < .65) continue;
              const c = center(points);
              if (candidates.some(v => distance(c, v.center) < 15 && Math.abs(area - v.area) < .12 * area)) continue;
              candidates.push({ points, center: c, area, width, height, ratio });
            } finally { contour.delete(); hull.delete(); quad.delete(); }
          }
        } finally { contours.delete(); hierarchy.delete(); }
      }
      }
      // Long observed borders still work when a stand interrupts the outer contour.
      if (!candidates.length) candidates.push(...lineCandidates(cv, combined, frameArea));
      candidates.sort((a, b) => b.area - a.area);
      if (!candidates.length) return { status: 'uncertain' };
      // Prefer the card's proportions over a slightly larger sleeve/stand edge.
      const largest = candidates[0];
      const best = candidates.filter(item => item.area >= largest.area * .94)
        .sort((a, b) => Math.abs(a.ratio - 63 / 88) - Math.abs(b.ratio - 63 / 88))[0];
      // Never mistake an inner artwork/text panel for the whole tight-framed card.
      for (const other of candidates) {
        if (other.area > .40 * best.area && distance(other.center, best.center) > Math.sqrt(best.area) * .65)
          return { status: 'multiple_cards' };
      }
      if ((best.width > best.height) !== (small.cols > small.rows) && best.area < .50 * frameArea)
        return { status: 'uncertain' };
      if (Math.min(best.width, best.height) / scale < 128) return { status: 'uncertain' };
      const outScale = Math.min(1, 1800 * scale / Math.max(best.width, best.height));
      return { status: 'cropped', points: best.points.map(p => p.map(value => value / scale)),
        width: Math.round(best.width / scale * outScale), height: Math.round(best.height / scale * outScale), areaFraction: best.area / frameArea };
    } finally { owned.reverse().forEach(mat => mat.delete()); }
  }
  function prepare(cv, image) {
    const source = cv.matFromImageData(image);
    try {
      const proposal = detect(cv, source);
      if (proposal.status !== 'cropped') return proposal;
      const { width, height } = proposal;
      const from = cv.matFromArray(4, 1, cv.CV_32FC2, proposal.points.flat());
      const to = cv.matFromArray(4, 1, cv.CV_32FC2, [0, 0, width - 1, 0, width - 1, height - 1, 0, height - 1]);
      let transform, output;
      try {
        transform = cv.getPerspectiveTransform(from, to); output = new cv.Mat();
        cv.warpPerspective(source, output, transform, new cv.Size(width, height), cv.INTER_LINEAR, cv.BORDER_CONSTANT, new cv.Scalar(255, 255, 255, 255));
        return { ...proposal, data: new Uint8ClampedArray(output.data) };
      } finally { from.delete(); to.delete(); transform?.delete(); output?.delete(); }
    } finally { source.delete(); }
  }
  root.CardoraAutoCrop = { detect, prepare };
})(globalThis);
