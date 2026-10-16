import { copyFileSync, mkdirSync } from 'node:fs';
mkdirSync('public/assets', {recursive:true});
copyFileSync('resources/css/app.css','public/assets/app.css');
copyFileSync('resources/js/app.js','public/assets/app.js');
console.log('CSS dan JavaScript aplikasi disalin.');
