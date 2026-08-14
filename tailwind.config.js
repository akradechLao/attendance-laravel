/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.vue",
        "./resources/**/*.js",
    ],
    theme: {
        extend: {
            colors: {
                navy: "#0f172a",
                cream: "#f0f4ff",
                blue: {
                    50: "#eff6ff",
                    100: "#dbeafe",
                    200: "#bfdbfe",
                    300: "#93c5fd",
                    400: "#60a5fa",
                    500: "#3b82f6",
                    600: "#2563eb",
                    700: "#1d4ed8",
                    800: "#1e40af",
                    900: "#1e3a8a",
                    950: "#172554",
                },
                gold: {
                    50: "#fdf9ea",
                    100: "#faf0c8",
                    200: "#f5df8f",
                    300: "#eecd57",
                    400: "#e6b93c",
                    500: "#d4af37",
                    600: "#b8912c",
                    700: "#936f25",
                    800: "#7a5824",
                    900: "#684921",
                    950: "#3c270e",
                },
                dark: {
                    50: "#f8fafc",
                    100: "#f1f5f9",
                    200: "#e2e8f0",
                    300: "#cbd5e1",
                    400: "#94a3b8",
                    500: "#64748b",
                    600: "#475569",
                    700: "#334155",
                    800: "#1e293b",
                    900: "#0f172a",
                    950: "#020617",
                },
                accent: {
                    sky: "#0ea5e9",
                    coral: "#f43f5e",
                    amber: "#f59e0b",
                    emerald: "#10b981",
                }
            }
        },
    },
    plugins: [],
}
