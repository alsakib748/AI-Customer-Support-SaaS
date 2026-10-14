<script setup>
import { useSeo } from '@/composables/useSeo';
import AnnouncementBar from '@/components/marketing/AnnouncementBar.vue';
import HeroSection from '@/components/marketing/HeroSection.vue';
import TrustSection from '@/components/marketing/TrustSection.vue';
import ProblemSection from '@/components/marketing/ProblemSection.vue';
import FeaturesSection from '@/components/marketing/FeaturesSection.vue';
import AISection from '@/components/marketing/AISection.vue';
import KnowledgeBaseSection from '@/components/marketing/KnowledgeBaseSection.vue';
import HumanAISection from '@/components/marketing/HumanAISection.vue';
import ConversationSection from '@/components/marketing/ConversationSection.vue';
import WidgetSection from '@/components/marketing/WidgetSection.vue';
import HowItWorks from '@/components/marketing/HowItWorks.vue';
import AnalyticsSection from '@/components/marketing/AnalyticsSection.vue';
import UseCasesSection from '@/components/marketing/UseCasesSection.vue';
import SecuritySection from '@/components/marketing/SecuritySection.vue';
import PricingSection from '@/components/marketing/PricingSection.vue';
import FAQSection from '@/components/marketing/FAQSection.vue';
import FinalCTA from '@/components/marketing/FinalCTA.vue';

useSeo({
    title: 'AI Customer Support Platform',
    description: 'Automate customer support with AI, empower agents, and manage conversations, tickets, knowledge and analytics from one platform.',
    path: '/'
});
</script>

<template>
    <AnnouncementBar />
    <HeroSection />
    <TrustSection />
    <ProblemSection />
    <FeaturesSection />
    <AISection />
    <KnowledgeBaseSection />
    <HumanAISection />
    <ConversationSection />
    <WidgetSection />
    <HowItWorks />
    <AnalyticsSection />
    <UseCasesSection />
    <SecuritySection />
    <PricingSection />
    <FAQSection />
    <FinalCTA />
</template>
