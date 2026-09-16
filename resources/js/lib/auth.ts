import type { UserRole } from "@/types/auth";

const roleRedirects: Record<UserRole, string> = {
    PATIENT: "/app",
    ADMIN: "/admin",
    RECEPTIONIST: "/admin",
    NURSE: "/admin",
    DOCTOR: "/admin",
    PHARMACY: "/admin",
    LAB: "/admin",
    STAFF: "/admin",
};

export function getAuthenticatedRedirect(
    role: UserRole,
): string {
    return roleRedirects[role] ?? "/login";
}