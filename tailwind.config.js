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
                blue: "#3b82f6",
                cream: "#f0f4ff",
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
                    blue: "#3b82f6",
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
