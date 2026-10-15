import { writable, derived } from 'svelte/store';

export interface Employee {
	id: string;
	employee_id: string;
	nama: string;
	telepon?: string | null;
	email?: string | null;
	departemen?: string | null;
	jabatan?: string | null;
	lokasi_kerja?: string | null;
	status: string;
}

const TOKEN_KEY = 'absensi_token';
const EMPLOYEE_KEY = 'absensi_employee';

function readInitial<T>(key: string): T | null {
	if (typeof localStorage === 'undefined') return null;
	try {
		const raw = localStorage.getItem(key);
		return raw ? (JSON.parse(raw) as T) : null;
	} catch {
		return null;
	}
}

/** Token Sanctum: disimpan di localStorage agar sesi bertahan, dikirim via Authorization header. */
export const token = writable<string | null>(readInitial<string>(TOKEN_KEY));
export const employee = writable<Employee | null>(readInitial<Employee>(EMPLOYEE_KEY));

export const isLoggedIn = derived(token, ($t) => !!$t);

token.subscribe((t) => {
	if (typeof localStorage === 'undefined') return;
	if (t) localStorage.setItem(TOKEN_KEY, JSON.stringify(t));
	else localStorage.removeItem(TOKEN_KEY);
});
employee.subscribe((e) => {
	if (typeof localStorage === 'undefined') return;
	if (e) localStorage.setItem(EMPLOYEE_KEY, JSON.stringify(e));
	else localStorage.removeItem(EMPLOYEE_KEY);
});

export function setSession(t: string, emp: Employee) {
	token.set(t);
	employee.set(emp);
}

export function clearSession() {
	token.set(null);
	employee.set(null);
}
