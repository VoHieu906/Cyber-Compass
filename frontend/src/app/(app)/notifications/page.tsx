import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { NotificationsView } from "@/features/notifications/components/notifications-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("notifications");
  return { title: t("title") };
}

/** Every notification, with read, read-all and delete (D34). */
export default function NotificationsPage() {
  return <NotificationsView />;
}
