"use client";

import { LogOut } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";
import { useCurrentUser, useLogout } from "@/features/auth/hooks";
import { AvatarUploader } from "./avatar-uploader";
import { PasswordForm } from "./password-form";
import { PreferencesPanel } from "./preferences-panel";
import { ProfileForm } from "./profile-form";
import { SettingsSection } from "./settings-section";

// RequireAuth (app layout) guarantees a user before these render.

export function ProfileSettings() {
  const t = useTranslations("settings");
  const { data: user } = useCurrentUser();
  if (!user) return null;
  return (
    <div className="flex flex-col gap-8">
      <SettingsSection title={t("profile.avatar")}>
        <AvatarUploader user={user} />
      </SettingsSection>
      <SettingsSection title={t("tabs.profile")}>
        <ProfileForm key={user.id} user={user} />
      </SettingsSection>
    </div>
  );
}

export function SecuritySettings() {
  const t = useTranslations("settings.security");
  const format = useFormatter();
  const { data: user } = useCurrentUser();
  const logout = useLogout();
  if (!user) return null;
  return (
    <div className="flex flex-col gap-8">
      <SettingsSection title={t("passwordTitle")} description={t("passwordLede")}>
        <PasswordForm />
      </SettingsSection>
      <SettingsSection
        title={t("sessionTitle")}
        description={
          user.created_at
            ? t("memberSince", { date: format.dateTime(new Date(user.created_at), { dateStyle: "long" }) })
            : undefined
        }
      >
        <div>
          <Button variant="secondary" loading={logout.isPending} onClick={() => logout.mutate()}>
            <LogOut aria-hidden />
            {t("logout")}
          </Button>
        </div>
      </SettingsSection>
    </div>
  );
}

export { PreferencesPanel as PreferenceSettings };
