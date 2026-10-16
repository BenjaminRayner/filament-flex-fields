/** Variable-width ink math adapted from Arc UI's public signature-pad registry.
 * Reference: https://uiarc.dev/r/signature-pad.json — no React/motion dependency.
 */
const add = (a, b) => [a[0] + b[0], a[1] + b[1]];
const sub = (a, b) => [a[0] - b[0], a[1] - b[1]];
const mul = (a, n) => [a[0] * n, a[1] * n];
const lerp = (a, b, t) => [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t];
const dist = (a, b) => Math.hypot(a[0] - b[0], a[1] - b[1]);
const norm = (a) => { const l = Math.hypot(a[0], a[1]); return l ? [a[0] / l, a[1] / l] : [0, 0]; };
const perp = (a) => [a[1], -a[0]];
const dot = (a, b) => a[0] * b[0] + a[1] * b[1];
const rotAround = (p, c, r) => {
  const s = Math.sin(r), co = Math.cos(r), px = p[0] - c[0], py = p[1] - c[1];
  return [px * co - py * s + c[0], px * s + py * co + c[1]];
};
const easeOut = (t) => t * (2 - t);

/**
 * Turns sampled points into the outline of a variable width stroke, in the spirit of perfect-freehand. Points are streamlined
 * toward the pointer, width follows pen pressure or, for a mouse or finger, speed, the ends taper, sharp turns get round joins,
 * and both ends get round caps. Returns the outline polygon; pass it to `outlineToPath` for SVG.
 */
export function getStrokeOutline(input, options) {
  const { size, thinning = .62, streamline = .42, simulatePressure = true, taperStart = 0, taperEnd = 0, last = true } = options;
  const [head] = input;
  if (!head) return [];
  const usePen = !simulatePressure && input.some(point => point.p > 0);

  // Streamline: each point eases toward the raw input, which removes jitter without a smoothing pass.
  const pts = [];
  let prev = [head.x, head.y], length = 0;
  const start = { at: prev, pressure: usePen ? head.p : .5, length: 0, vector: [1, 0] };
  pts.push(start);
  for (let i = 1; i < input.length; i++) {
    const sample = input[i];
    const raw = [sample.x, sample.y];
    const at = i === input.length - 1 && last ? raw : lerp(prev, raw, 1 - streamline);
    const d = dist(at, prev);
    if (d < .4) continue;
    length += d;
    pts.push({ at, pressure: usePen ? sample.p : .5, length, vector: norm(sub(prev, at)) });
    prev = at;
  }
  const second = pts[1];
  if (second) start.vector = second.vector;
  const total = length;

  // A dot: a round mark sized by the first pressure.
  if (pts.length === 1) {
    const r = Math.max(.6, size * (.5 - thinning * (.5 - start.pressure)) * .9);
    return Array.from({ length: 18 }, (_, i) => add(start.at, mul([Math.cos(i / 18 * Math.PI * 2), Math.sin(i / 18 * Math.PI * 2)], r)));
  }

  const left = [], right = [];
  let pressure = start.pressure, prevVector = start.vector;
  
  for (let i = 0; i < pts.length; i++) {
    const point = pts[i];
    if (!usePen) {
      // Speed thins the line: a long step between samples reads as a fast stroke.
      const step = i === 0 ? 0 : dist(point.at, pts[i - 1].at);
      const speed = Math.min(1, step / size);
      const target = Math.min(1, 1 - speed);
      pressure = Math.min(1, pressure + (target - pressure) * speed * .3);
    } else {
      pressure = point.pressure;
    }
    let radius = Math.max(.25, size * (.5 - thinning * (.5 - pressure)));
    const ts = taperStart ? easeOut(Math.min(1, point.length / taperStart)) : 1;
    const te = taperEnd && last ? easeOut(Math.min(1, (total - point.length) / taperEnd)) : 1;
    radius = Math.max(.12 * size, radius * Math.min(ts, te));


    const next = pts[i + 1]?.vector ?? point.vector;
    const offset = mul(perp(lerp(next, point.vector, .5)), radius);
    // A sharp turn: sweep round the corner so the outline does not pinch.
    if (i > 0 && i < pts.length - 1 && dot(point.vector, next) < -.2) {
      const corner = mul(perp(prevVector), radius);
      for (let t = 0; t <= 1; t += 1 / 12) {
        left.push(rotAround(sub(point.at, corner), point.at, Math.PI * t));
        right.push(rotAround(add(point.at, corner), point.at, -Math.PI * t));
      }
    } else {
      left.push(sub(point.at, offset));
      right.push(add(point.at, offset));
    }
    prevVector = point.vector;
  }

  // Round caps: half circles swept around the first and last points, on the side facing away from the stroke.
  const cap = (center, from, to, away) => {
    const out = [];
    const r = dist(center, from), start = Math.atan2(from[1] - center[1], from[0] - center[0]);
    const mid = start + Math.PI / 2;
    const sign = dot([Math.cos(mid), Math.sin(mid)], away) >= 0 ? 1 : -1;
    for (let k = 1; k < 12; k++) {
      const a = start + sign * Math.PI * k / 12;
      out.push([center[0] + Math.cos(a) * r, center[1] + Math.sin(a) * r]);
    }
    out.push(to);
    return out;
  };
  const end = pts[pts.length - 1];
  const endCap = cap(end.at, left[left.length - 1], right[right.length - 1], mul(end.vector, -1));
  const startCap = cap(start.at, right[0], left[0], start.vector);
  return [...left, ...endCap, ...right.reverse(), ...startCap];
}

const round = (value) => Math.round(value * 100) / 100;
/** Draws an outline as one smooth closed path, with quadratic curves through the midpoints of its edges. */
export function outlineToPath(points) {
  if (points.length < 3) return "";
  let d = `M${round(points[0][0])} ${round(points[0][1])} Q`;
  for (let i = 0; i < points.length; i++) {
    const [x0, y0] = points[i], [x1, y1] = points[(i + 1) % points.length];
    d += `${round(x0)} ${round(y0)} ${round((x0 + x1) / 2)} ${round((y0 + y1) / 2)} `;
  }
  return `${d}Z`;
}


export function inkPath(stroke, last = true) {
    const points = stroke.points, size = stroke.width
    const pen = points.some(point => point.p > 0)
    return outlineToPath(getStrokeOutline(points, { size, simulatePressure: !pen, streamline: stroke.smoothing === false ? 0 : .42,
        taperStart: size * 1.5, taperEnd: pen ? 0 : size * 5, last }))
}
