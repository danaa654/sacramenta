import { ref, watchEffect } from 'vue';

const STORAGE_KEY = 'sacramenta-theme';

const isDark = ref(document.documentElement.classList.contains('dark'));

watchEffect(() => {
    document.documentElement.classList.toggle('dark', isDark.value);
    localStorage.setItem(STORAGE_KEY, isDark.value ? 'dark' : 'light');
});

export function useAppearance() {
    function toggle() {
        isDark.value = !isDark.value;
    }

    function set(theme) {
        isDark.value = theme === 'dark';
    }

    return { isDark, toggle, set };
}