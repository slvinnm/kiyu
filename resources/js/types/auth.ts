export type UserRole =
    | "patient"
    | "admin"
    | "receptionist"
    | "nurse"
    | "doctor"
    | "pharmacy"
    | "lab"
    | "staff";

export type PatientProfile = {
    id: string;
    name: string;
    email: string;
    national_id?: string | null;
    date_of_birth?: string | null;
    gender?: string | null;
    phone?: string | null;
    address?: string | null;
};

export type User = {
    id: string;
    name: string;
    email: string;
    role: UserRole;
    profile?: PatientProfile | null;
};

export type AuthResponse = {
    success: boolean;
    message: string;
    data: {
        token: string;
        user: User;
    };
};

export type AuthMeResponse = {
    success: boolean;
    message: string;
    data: User;
};

export type ApiErrorResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};