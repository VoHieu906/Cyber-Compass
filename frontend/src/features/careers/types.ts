import type { CourseSummary, DomainRef } from "@/features/catalog/types";

export type CareerDomain = DomainRef & { required_level: 1 | 2 | 3 };

export type CareerSummary = {
  slug: string;
  title: string;
  summary: string;
  domains: CareerDomain[];
  topic_count: number;
};

export type RoadmapTopic = {
  slug: string;
  title: string;
  summary: string;
  domain: DomainRef;
  lesson_count: number;
  requires: string[];
};

export type CareerDetail = CareerSummary & {
  description: string;
  responsibilities: string[];
  skills: { slug: string; name: string }[];
  suggested_courses: CourseSummary[];
  roadmap: { stages: { stage: number; topics: RoadmapTopic[] }[] };
};
