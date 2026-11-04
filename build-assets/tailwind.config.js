// Compiled Tailwind build (replaces the CDN script). Rebuild after changing views:  npm run build:css
module.exports = {
  content: ['./resources/views/**/*.blade.php', './app/**/*.php', './config/**/*.php'],
  theme: { extend: {} },
  plugins: [],
};
