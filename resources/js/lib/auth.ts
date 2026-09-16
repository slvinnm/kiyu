import type { UserRole } from "@/types/auth";

const roleRedirects: Record<UserRole, string> = {
    patient: "/app",
    admin: "/admin",
    receptionist: "/admin",
    nurse: "/admin",
    doctor: "/admin",
    pharmacy: "/admin",
    lab: "/admin",
    staff: "/admin",
};

export function getAuthenticatedRedirect(
    role: UserRole,
): string {
    return roleRedirects[role] ?? "/login";
}