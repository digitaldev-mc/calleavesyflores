import fs from 'fs';
import { PNG } from 'pngjs';

const input = new URL('../logo.png', import.meta.url);
const THRESHOLD = 40;

function isBgBlack(r, g, b) {
  return r <= THRESHOLD && g <= THRESHOLD && b <= THRESHOLD;
}

const buf = fs.readFileSync(input);
const png = PNG.sync.read(buf);
const { width, height, data } = png;
const visited = new Uint8Array(width * height);
const queue = [];

function idx(x, y) {
  return y * width + x;
}

function push(x, y) {
  const i = idx(x, y);
  if (visited[i]) return;
  const p = i * 4;
  if (!isBgBlack(data[p], data[p + 1], data[p + 2])) return;
  visited[i] = 1;
  queue.push(i);
}

for (let x = 0; x < width; x++) {
  push(x, 0);
  push(x, height - 1);
}
for (let y = 0; y < height; y++) {
  push(0, y);
  push(width - 1, y);
}

while (queue.length) {
  const i = queue.pop();
  const x = i % width;
  const y = (i - x) / width;
  const p = i * 4;
  data[p + 3] = 0;

  if (x > 0) push(x - 1, y);
  if (x < width - 1) push(x + 1, y);
  if (y > 0) push(x, y - 1);
  if (y < height - 1) push(x, y + 1);
}

fs.writeFileSync(input, PNG.sync.write(png));
console.log('Logo actualizado con fondo transparente:', input.pathname);
