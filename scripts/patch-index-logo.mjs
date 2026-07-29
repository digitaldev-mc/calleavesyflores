import fs from 'fs';

const file = new URL('../index.html', import.meta.url);
let html = fs.readFileSync(file, 'utf8');
const before = html.length;
html = html.replace(/src="data:image\/png;base64,[^"]+"/g, 'src="logo.png"');
fs.writeFileSync(file, html);
const count = (before - html.length);
console.log(`index.html actualizado (${count} bytes menos de base64)`);
