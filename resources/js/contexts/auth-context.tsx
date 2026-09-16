import {
    createContext,
    useCallback,
    useEffect,
    useMemo,
    useState,
} from "react";
import type { ReactNode } from "react";
import { router } from "@inertiajs/react";
import axios from "axios";

import * as authApi from "@/api/auth";
import { getAuthenticatedRedirect } from "@/lib/auth";
import type { User } from "@/types/auth";

type AuthContextValue = {
    user: User | null;
    loading: boolean;
    isAuthenticated: boolean;
    login: (email: string, password: string) => Promise<void>;
    logout: () => Promise<void>;
    refreshUser: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

type AuthProviderProps = {
    children: ReactNode;
};

export function AuthProvider({
    children,
}: AuthProviderProps) {
    const [user, setUser] = useState<User | null>(null);
    const [loading, setLoading] = useState(true);

    const clearAuthentication = useCallback(() => {
        localStorage.removeItem("auth_token");
        setUser(null);
    }, []);

    const refreshUser = useCallback(async () => {
        const token = localStorage.getItem("auth_token");

        if (!token) {
            setUser(null);
            return;
        }

        try {
            const response = await authApi.me();

            setUser(response.data);
        } catch (error) {
            if (
                axios.isAxiosError(error) &&
                error.response?.status === 401
            ) {
                clearAuthentication();
            } else {
                throw error;
            }
        }
    }, [clearAuthentication]);

    useEffect(() => {
        let mounted = true;

        async function initialize() {
            try {
                await refreshUser();
            } catch (error) {
                console.error(
                    "Failed to initialize authentication.",
                    error,
                );
            } finally {
                if (mounted) {
                    setLoading(false);
                }
            }
        }

        initialize();

        return () => {
            mounted = false;
        };
    }, [refreshUser]);

    async function login(
        email: string,
        password: string,
    ): Promise<void> {
        const response = await authApi.login({
            email,
            password,
        });

        const { token, user } = response.data;

        localStorage.setItem("auth_token", token);
        setUser(user);

        router.visit(
            getAuthenticatedRedirect(user.role),
        );
    }

    async function logout(): Promise<void> {
        try {
            const token = localStorage.getItem("auth_token");

            if (token) {
                await authApi.logout();
            }
        } catch (error) {
            console.error(
                "Failed to revoke authentication token.",
                error,
            );
        } finally {
            clearAuthentication();
            router.visit("/login");
        }
    }

    const value = useMemo<AuthContextValue>(
        () => ({
            user,
            loading,
            isAuthenticated: user !== null,
            login,
            logout,
            refreshUser,
        }),
        [
            user,
            loading,
            refreshUser,
        ],
    );

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
}

export { AuthContext };