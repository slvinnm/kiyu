import api from "@/api/client";
import type {
    AuthMeResponse,
    AuthResponse,
} from "@/types/auth";

type LoginPayload = {
    email: string;
    password: string;
};

type LogoutResponse = {
    success: boolean;
    message: string;
};

export async function login(
    payload: LoginPayload,
): Promise<AuthResponse> {
    const response = await api.post<AuthResponse>(
        "/auth/login",
        payload,
    );

    return response.data;
}

export async function me(): Promise<AuthMeResponse> {
    const response = await api.get<AuthMeResponse>(
        "/auth/me",
    );

    return response.data;
}

export async function logout(): Promise<LogoutResponse> {
    const response = await api.post<LogoutResponse>(
        "/auth/logout",
    );

    return response.data;
}