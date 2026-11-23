export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    canManageUsers: boolean;
    registrationUrl: string | null;
};

export type PasskeyRoutes = {
    options: string;
    submit: string;
};

export type Passkey = {
    id: number;
    name: string;
    deleteUrl: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorRoutes = {
    enable: string;
    disable: string;
    confirm: string;
    qrCode: string;
    secretKey: string;
    recoveryCodes: string;
    regenerateRecoveryCodes: string;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
