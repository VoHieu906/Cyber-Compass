import { LessonReader } from "@/features/learning/components/lesson-reader";

/** Lessons are behind sign-in: reading records progress against the learner's account. */
export default async function LessonPage({ params }: PageProps<"/learn/[slug]">) {
  const { slug } = await params;
  return <LessonReader key={slug} slug={slug} />;
}
