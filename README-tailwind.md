# Tailwind CSS setup

Tailwind is integrated via the **standalone CLI binary** (`tailwindcss.exe`, Windows x64).
No Node, npm, or package.json required. The binary is gitignored and stays on your machine.

The compiled CSS (`public/styles/tailwind.css`) is committed to the repo and served by nginx as a static file.
Theme tokens (colors, radius, font) are defined in `src/tailwind.css` using Tailwind v4 `@theme`.

## Re-compile after editing styles

Run the watcher while developing:

```
./tailwindcss.exe -i src/tailwind.css -o public/styles/tailwind.css --watch
```

> **Note:** The compiled CSS is committed to the repo. Only re-run the watcher when modifying styles or adding new Tailwind classes to templates.
