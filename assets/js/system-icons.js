/* ============================================================
   system-icons.js — Iconos de línea monocromos del sistema
   Reemplaza los emojis de color por dibujos (SVG) que heredan
   el color del texto. Solo se carga en el panel del sistema.
   Iconos: Lucide (https://lucide.dev) — licencia ISC.
   ============================================================ */
(function () {
  'use strict';

  var ICONS = {
    "arrow-down": "<path d=\"M12 5v14\"/> <path d=\"m19 12-7 7-7-7\"/>",
    "arrow-left": "<path d=\"m12 19-7-7 7-7\"/> <path d=\"M19 12H5\"/>",
    "arrow-right": "<path d=\"M5 12h14\"/> <path d=\"m12 5 7 7-7 7\"/>",
    "ban": "<circle cx=\"12\" cy=\"12\" r=\"10\"/> <path d=\"m4.9 4.9 14.2 14.2\"/>",
    "banknote": "<rect width=\"20\" height=\"12\" x=\"2\" y=\"6\" rx=\"2\"/> <circle cx=\"12\" cy=\"12\" r=\"2\"/> <path d=\"M6 12h.01M18 12h.01\"/>",
    "bell": "<path d=\"M10.268 21a2 2 0 0 0 3.464 0\"/> <path d=\"M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326\"/>",
    "bolt": "<path d=\"M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z\"/> <circle cx=\"12\" cy=\"12\" r=\"4\"/>",
    "briefcase": "<path d=\"M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16\"/> <rect width=\"20\" height=\"14\" x=\"2\" y=\"6\" rx=\"2\"/>",
    "building": "<rect width=\"16\" height=\"20\" x=\"4\" y=\"2\" rx=\"2\" ry=\"2\"/> <path d=\"M9 22v-4h6v4\"/> <path d=\"M8 6h.01\"/> <path d=\"M16 6h.01\"/> <path d=\"M12 6h.01\"/> <path d=\"M12 10h.01\"/> <path d=\"M12 14h.01\"/> <path d=\"M16 10h.01\"/> <path d=\"M16 14h.01\"/> <path d=\"M8 10h.01\"/> <path d=\"M8 14h.01\"/>",
    "building-2": "<path d=\"M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z\"/> <path d=\"M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2\"/> <path d=\"M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2\"/> <path d=\"M10 6h4\"/> <path d=\"M10 10h4\"/> <path d=\"M10 14h4\"/> <path d=\"M10 18h4\"/>",
    "calendar-days": "<path d=\"M8 2v4\"/> <path d=\"M16 2v4\"/> <rect width=\"18\" height=\"18\" x=\"3\" y=\"4\" rx=\"2\"/> <path d=\"M3 10h18\"/> <path d=\"M8 14h.01\"/> <path d=\"M12 14h.01\"/> <path d=\"M16 14h.01\"/> <path d=\"M8 18h.01\"/> <path d=\"M12 18h.01\"/> <path d=\"M16 18h.01\"/>",
    "camera": "<path d=\"M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z\"/> <circle cx=\"12\" cy=\"13\" r=\"3\"/>",
    "chart-column": "<path d=\"M3 3v16a2 2 0 0 0 2 2h16\"/> <path d=\"M18 17V9\"/> <path d=\"M13 17V5\"/> <path d=\"M8 17v-3\"/>",
    "check": "<path d=\"M20 6 9 17l-5-5\"/>",
    "circle": "<circle cx=\"12\" cy=\"12\" r=\"10\"/>",
    "circle-check": "<circle cx=\"12\" cy=\"12\" r=\"10\"/> <path d=\"m9 12 2 2 4-4\"/>",
    "circle-x": "<circle cx=\"12\" cy=\"12\" r=\"10\"/> <path d=\"m15 9-6 6\"/> <path d=\"m9 9 6 6\"/>",
    "clipboard-list": "<rect width=\"8\" height=\"4\" x=\"8\" y=\"2\" rx=\"1\" ry=\"1\"/> <path d=\"M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2\"/> <path d=\"M12 11h4\"/> <path d=\"M12 16h4\"/> <path d=\"M8 11h.01\"/> <path d=\"M8 16h.01\"/>",
    "credit-card": "<rect width=\"20\" height=\"14\" x=\"2\" y=\"5\" rx=\"2\"/> <line x1=\"2\" x2=\"22\" y1=\"10\" y2=\"10\"/>",
    "dollar-sign": "<line x1=\"12\" x2=\"12\" y1=\"2\" y2=\"22\"/> <path d=\"M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6\"/>",
    "eye": "<path d=\"M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0\"/> <circle cx=\"12\" cy=\"12\" r=\"3\"/>",
    "file-text": "<path d=\"M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z\"/> <path d=\"M14 2v4a2 2 0 0 0 2 2h4\"/> <path d=\"M10 9H8\"/> <path d=\"M16 13H8\"/> <path d=\"M16 17H8\"/>",
    "globe": "<circle cx=\"12\" cy=\"12\" r=\"10\"/> <path d=\"M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20\"/> <path d=\"M2 12h20\"/>",
    "hand": "<path d=\"M18 11V6a2 2 0 0 0-2-2a2 2 0 0 0-2 2\"/> <path d=\"M14 10V4a2 2 0 0 0-2-2a2 2 0 0 0-2 2v2\"/> <path d=\"M10 10.5V6a2 2 0 0 0-2-2a2 2 0 0 0-2 2v8\"/> <path d=\"M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15\"/>",
    "hard-hat": "<path d=\"M10 10V5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5\"/> <path d=\"M14 6a6 6 0 0 1 6 6v3\"/> <path d=\"M4 15v-3a6 6 0 0 1 6-6\"/> <rect x=\"2\" y=\"15\" width=\"20\" height=\"4\" rx=\"1\"/>",
    "hash": "<line x1=\"4\" x2=\"20\" y1=\"9\" y2=\"9\"/> <line x1=\"4\" x2=\"20\" y1=\"15\" y2=\"15\"/> <line x1=\"10\" x2=\"8\" y1=\"3\" y2=\"21\"/> <line x1=\"16\" x2=\"14\" y1=\"3\" y2=\"21\"/>",
    "hourglass": "<path d=\"M5 22h14\"/> <path d=\"M5 2h14\"/> <path d=\"M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22\"/> <path d=\"M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2\"/>",
    "house": "<path d=\"M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8\"/> <path d=\"M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z\"/>",
    "lock": "<rect width=\"18\" height=\"11\" x=\"3\" y=\"11\" rx=\"2\" ry=\"2\"/> <path d=\"M7 11V7a5 5 0 0 1 10 0v4\"/>",
    "mail": "<rect width=\"20\" height=\"16\" x=\"2\" y=\"4\" rx=\"2\"/> <path d=\"m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7\"/>",
    "mail-open": "<path d=\"M21.2 8.4c.5.38.8.97.8 1.6v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V10a2 2 0 0 1 .8-1.6l8-6a2 2 0 0 1 2.4 0l8 6Z\"/> <path d=\"m22 10-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 10\"/>",
    "mail-plus": "<path d=\"M22 13V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12c0 1.1.9 2 2 2h8\"/> <path d=\"m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7\"/> <path d=\"M19 16v6\"/> <path d=\"M16 19h6\"/>",
    "map": "<path d=\"M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z\"/> <path d=\"M15 5.764v15\"/> <path d=\"M9 3.236v15\"/>",
    "map-pin": "<path d=\"M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0\"/> <circle cx=\"12\" cy=\"10\" r=\"3\"/>",
    "megaphone": "<path d=\"m3 11 18-5v12L3 14v-3z\"/> <path d=\"M11.6 16.8a3 3 0 1 1-5.8-1.6\"/>",
    "menu": "<line x1=\"4\" x2=\"20\" y1=\"12\" y2=\"12\"/> <line x1=\"4\" x2=\"20\" y1=\"6\" y2=\"6\"/> <line x1=\"4\" x2=\"20\" y1=\"18\" y2=\"18\"/>",
    "message-circle": "<path d=\"M7.9 20A9 9 0 1 0 4 16.1L2 22Z\"/>",
    "milk": "<path d=\"M8 2h8\"/> <path d=\"M9 2v2.789a4 4 0 0 1-.672 2.219l-.656.984A4 4 0 0 0 7 10.212V20a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-9.789a4 4 0 0 0-.672-2.219l-.656-.984A4 4 0 0 1 15 4.788V2\"/> <path d=\"M7 15a6.472 6.472 0 0 1 5 0 6.47 6.47 0 0 0 5 0\"/>",
    "notebook-pen": "<path d=\"M13.4 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7.4\"/> <path d=\"M2 6h4\"/> <path d=\"M2 10h4\"/> <path d=\"M2 14h4\"/> <path d=\"M2 18h4\"/> <path d=\"M21.378 5.626a1 1 0 1 0-3.004-3.004l-5.01 5.012a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506z\"/>",
    "octagon-x": "<path d=\"m15 9-6 6\"/> <path d=\"M2.586 16.726A2 2 0 0 1 2 15.312V8.688a2 2 0 0 1 .586-1.414l4.688-4.688A2 2 0 0 1 8.688 2h6.624a2 2 0 0 1 1.414.586l4.688 4.688A2 2 0 0 1 22 8.688v6.624a2 2 0 0 1-.586 1.414l-4.688 4.688a2 2 0 0 1-1.414.586H8.688a2 2 0 0 1-1.414-.586z\"/> <path d=\"m9 9 6 6\"/>",
    "package": "<path d=\"M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z\"/> <path d=\"M12 22V12\"/> <path d=\"m3.3 7 7.703 4.734a2 2 0 0 0 1.994 0L20.7 7\"/> <path d=\"m7.5 4.27 9 5.15\"/>",
    "paperclip": "<path d=\"M13.234 20.252 21 12.3\"/> <path d=\"m16 6-8.414 8.586a2 2 0 0 0 0 2.828 2 2 0 0 0 2.828 0l8.414-8.586a4 4 0 0 0 0-5.656 4 4 0 0 0-5.656 0l-8.415 8.585a6 6 0 1 0 8.486 8.486\"/>",
    "pencil": "<path d=\"M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z\"/> <path d=\"m15 5 4 4\"/>",
    "pin": "<path d=\"M12 17v5\"/> <path d=\"M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z\"/>",
    "plug": "<path d=\"M12 22v-5\"/> <path d=\"M9 8V2\"/> <path d=\"M15 8V2\"/> <path d=\"M18 8v5a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4V8Z\"/>",
    "plus": "<path d=\"M5 12h14\"/> <path d=\"M12 5v14\"/>",
    "printer": "<path d=\"M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2\"/> <path d=\"M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6\"/> <rect x=\"6\" y=\"14\" width=\"12\" height=\"8\" rx=\"1\"/>",
    "receipt-text": "<path d=\"M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z\"/> <path d=\"M14 8H8\"/> <path d=\"M16 12H8\"/> <path d=\"M13 16H8\"/>",
    "recycle": "<path d=\"M7 19H4.815a1.83 1.83 0 0 1-1.57-.881 1.785 1.785 0 0 1-.004-1.784L7.196 9.5\"/> <path d=\"M11 19h8.203a1.83 1.83 0 0 0 1.556-.89 1.784 1.784 0 0 0 0-1.775l-1.226-2.12\"/> <path d=\"m14 16-3 3 3 3\"/> <path d=\"M8.293 13.596 7.196 9.5 3.1 10.598\"/> <path d=\"m9.344 5.811 1.093-1.892A1.83 1.83 0 0 1 11.985 3a1.784 1.784 0 0 1 1.546.888l3.943 6.843\"/> <path d=\"m13.378 9.633 4.096 1.098 1.097-4.096\"/>",
    "refresh-cw": "<path d=\"M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8\"/> <path d=\"M21 3v5h-5\"/> <path d=\"M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16\"/> <path d=\"M8 16H3v5\"/>",
    "repeat": "<path d=\"m17 2 4 4-4 4\"/> <path d=\"M3 11v-1a4 4 0 0 1 4-4h14\"/> <path d=\"m7 22-4-4 4-4\"/> <path d=\"M21 13v1a4 4 0 0 1-4 4H3\"/>",
    "rocket": "<path d=\"M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z\"/> <path d=\"m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z\"/> <path d=\"M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0\"/> <path d=\"M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5\"/>",
    "route": "<circle cx=\"6\" cy=\"19\" r=\"3\"/> <path d=\"M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15\"/> <circle cx=\"18\" cy=\"5\" r=\"3\"/>",
    "save": "<path d=\"M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z\"/> <path d=\"M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7\"/> <path d=\"M7 3v4a1 1 0 0 0 1 1h7\"/>",
    "scroll-text": "<path d=\"M15 12h-5\"/> <path d=\"M15 8h-5\"/> <path d=\"M19 17V5a2 2 0 0 0-2-2H4\"/> <path d=\"M8 21h12a2 2 0 0 0 2-2v-1a1 1 0 0 0-1-1H11a1 1 0 0 0-1 1v1a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v2a1 1 0 0 0 1 1h3\"/>",
    "search": "<circle cx=\"11\" cy=\"11\" r=\"8\"/> <path d=\"m21 21-4.3-4.3\"/>",
    "shield": "<path d=\"M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z\"/>",
    "shirt": "<path d=\"M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z\"/>",
    "siren": "<path d=\"M7 18v-6a5 5 0 1 1 10 0v6\"/> <path d=\"M5 21a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-1a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2z\"/> <path d=\"M21 12h1\"/> <path d=\"M18.5 4.5 18 5\"/> <path d=\"M2 12h1\"/> <path d=\"M12 2v1\"/> <path d=\"m4.929 4.929.707.707\"/> <path d=\"M12 12v6\"/>",
    "sparkle": "<path d=\"M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z\"/>",
    "sparkles": "<path d=\"M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z\"/> <path d=\"M20 3v4\"/> <path d=\"M22 5h-4\"/> <path d=\"M4 17v2\"/> <path d=\"M5 18H3\"/>",
    "sprout": "<path d=\"M7 20h10\"/> <path d=\"M10 20c5.5-2.5.8-6.4 3-10\"/> <path d=\"M9.5 9.4c1.1.8 1.8 2.2 2.3 3.7-2 .4-3.5.4-4.8-.3-1.2-.6-2.3-1.9-3-4.2 2.8-.5 4.4 0 5.5.8z\"/> <path d=\"M14.1 6a7 7 0 0 0-1.1 4c1.9-.1 3.3-.6 4.3-1.4 1-1 1.6-2.3 1.7-4.6-2.7.1-4 1-4.9 2z\"/>",
    "tag": "<path d=\"M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z\"/> <circle cx=\"7.5\" cy=\"7.5\" r=\".5\" fill=\"currentColor\"/>",
    "trending-down": "<polyline points=\"22 17 13.5 8.5 8.5 13.5 2 7\"/> <polyline points=\"16 17 22 17 22 11\"/>",
    "trending-up": "<polyline points=\"22 7 13.5 15.5 8.5 10.5 2 17\"/> <polyline points=\"16 7 22 7 22 13\"/>",
    "triangle-alert": "<path d=\"m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3\"/> <path d=\"M12 9v4\"/> <path d=\"M12 17h.01\"/>",
    "undo-2": "<path d=\"M9 14 4 9l5-5\"/> <path d=\"M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5a5.5 5.5 0 0 1-5.5 5.5H11\"/>",
    "upload": "<path d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\"/> <polyline points=\"17 8 12 3 7 8\"/> <line x1=\"12\" x2=\"12\" y1=\"3\" y2=\"15\"/>",
    "user": "<path d=\"M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2\"/> <circle cx=\"12\" cy=\"7\" r=\"4\"/>",
    "users": "<path d=\"M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2\"/> <circle cx=\"9\" cy=\"7\" r=\"4\"/> <path d=\"M22 21v-2a4 4 0 0 0-3-3.87\"/> <path d=\"M16 3.13a4 4 0 0 1 0 7.75\"/>",
    "wine": "<path d=\"M8 22h8\"/> <path d=\"M7 10h10\"/> <path d=\"M12 15v7\"/> <path d=\"M12 15a5 5 0 0 0 5-5c0-2-.5-4-2-8H9c-1.5 4-2 6-2 8a5 5 0 0 0 5 5Z\"/>",
    "x": "<path d=\"M18 6 6 18\"/> <path d=\"m6 6 12 12\"/>",
    "zap": "<path d=\"M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z\"/>"
  };

  // Emoji / símbolo -> nombre del icono de línea
  var EMOJI_ICONS = {
    "✅": "circle-check",
    "⏳": "hourglass",
    "📄": "file-text",
    "🛡": "shield",
    "🏠": "house",
    "📋": "clipboard-list",
    "📎": "paperclip",
    "🏘": "building-2",
    "💰": "banknote",
    "🖨": "printer",
    "❌": "circle-x",
    "👤": "user",
    "🛣": "route",
    "📤": "upload",
    "📊": "chart-column",
    "✏": "pencil",
    "📷": "camera",
    "📍": "map-pin",
    "♻": "recycle",
    "📦": "package",
    "📜": "scroll-text",
    "🔄": "refresh-cw",
    "⚠": "triangle-alert",
    "💬": "message-circle",
    "👁": "eye",
    "🧾": "receipt-text",
    "📅": "calendar-days",
    "🔍": "search",
    "⛔": "ban",
    "🏙": "building-2",
    "📩": "mail",
    "💼": "briefcase",
    "🔔": "bell",
    "🔁": "repeat",
    "🏷": "tag",
    "📨": "mail-plus",
    "📉": "trending-down",
    "💾": "save",
    "➕": "plus",
    "🛑": "octagon-x",
    "🗺": "map",
    "🚫": "ban",
    "💵": "dollar-sign",
    "🔻": "arrow-down",
    "🏡": "house",
    "📭": "mail-open",
    "🧴": "milk",
    "🍾": "wine",
    "🔩": "bolt",
    "🌱": "sprout",
    "🔌": "plug",
    "👕": "shirt",
    "🏢": "building",
    "💳": "credit-card",
    "✉": "mail",
    "🚀": "rocket",
    "📝": "notebook-pen",
    "🌐": "globe",
    "🚨": "siren",
    "👏": "hand",
    "📌": "pin",
    "👥": "users",
    "✨": "sparkles",
    "📈": "trending-up",
    "👷": "hard-hat",
    "🟡": "circle",
    "💥": "zap",
    "🔒": "lock",
    "🟢": "circle",
    "🔢": "hash",
    "📣": "megaphone",
    "✓": "check",
    "✗": "x",
    "✕": "x",
    "⬅": "arrow-left",
    "☰": "menu",
    "→": "arrow-right",
    "←": "arrow-left",
    "↩": "undo-2",
    "✦": "sparkle"
  };

  var SKIP_TAGS = {
    SCRIPT: 1, STYLE: 1, TEXTAREA: 1, INPUT: 1, OPTION: 1, SELECT: 1,
    TITLE: 1, CODE: 1, PRE: 1, SVG: 1, PATH: 1, CIRCLE: 1, RECT: 1,
    LINE: 1, POLYLINE: 1, POLYGON: 1, ELLIPSE: 1, G: 1
  };

  var keys = Object.keys(EMOJI_ICONS).sort(function (a, b) {
    return b.length - a.length;
  });

  function escapeReg(s) {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  var emojiRe = new RegExp('(' + keys.map(escapeReg).join('|') + ')\\uFE0F?', 'g');
  var emojiTest = new RegExp(emojiRe.source);

  function createIcon(name) {
    var span = document.createElement('span');
    span.className = 'sys-icon';
    span.setAttribute('aria-hidden', 'true');
    span.innerHTML =
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
      'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
      (ICONS[name] || ICONS.circle) + '</svg>';
    return span;
  }

  function replaceTextNode(node) {
    var text = node.nodeValue;
    if (!text) return;

    emojiRe.lastIndex = 0;
    if (!emojiRe.test(text)) return;

    emojiRe.lastIndex = 0;
    var frag = document.createDocumentFragment();
    var last = 0;
    var match;

    while ((match = emojiRe.exec(text)) !== null) {
      if (match.index > last) {
        frag.appendChild(document.createTextNode(text.slice(last, match.index)));
      }
      frag.appendChild(createIcon(EMOJI_ICONS[match[1]]));
      last = match.index + match[0].length;
    }

    if (last < text.length) {
      frag.appendChild(document.createTextNode(text.slice(last)));
    }

    if (node.parentNode) node.parentNode.replaceChild(frag, node);
  }

  function shouldSkip(el) {
    return !el || SKIP_TAGS[el.tagName] ||
      (el.classList && el.classList.contains('sys-icon'));
  }

  function processRoot(root) {
    if (!root) return;

    if (root.nodeType === 3) {
      replaceTextNode(root);
      return;
    }

    if (root.nodeType !== 1 && root.nodeType !== 9 && root.nodeType !== 11) return;
    if (root.nodeType === 1 && shouldSkip(root)) return;

    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, function (node) {
      var parent = node.parentNode;
      if (shouldSkip(parent) || (parent && parent.closest && parent.closest('.sys-icon'))) {
        return NodeFilter.FILTER_REJECT;
      }
      return node.nodeValue && emojiTest.test(node.nodeValue)
        ? NodeFilter.FILTER_ACCEPT
        : NodeFilter.FILTER_REJECT;
    });

    var nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    for (var i = 0; i < nodes.length; i++) replaceTextNode(nodes[i]);
  }

  function ensureStyles() {
    if (document.getElementById('sys-icons-style')) return;
    var style = document.createElement('style');
    style.id = 'sys-icons-style';
    style.textContent =
      '.sys-icon{display:inline-flex;width:1em;height:1em;align-items:center;' +
      'justify-content:center;vertical-align:-0.15em;line-height:1;flex:0 0 auto}' +
      '.sys-icon svg{width:100%;height:100%;display:block;fill:none;stroke:currentColor}';
    (document.head || document.documentElement).appendChild(style);
  }

  function init() {
    ensureStyles();
    processRoot(document.body);

    if (typeof MutationObserver === 'undefined') return;
    var observer = new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i++) {
        var added = mutations[i].addedNodes;
        for (var j = 0; j < added.length; j++) {
          var node = added[j];
          if (node.nodeType === 1 && shouldSkip(node)) continue;
          if (node.parentNode && node.parentNode.closest &&
              node.parentNode.closest('.sys-icon')) continue;
          processRoot(node);
        }
      }
    });

    observer.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

