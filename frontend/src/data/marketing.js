import { Sparkles, MessageSquare, Ticket, BookOpen, Users, BarChart3, Layers, KeyRound, Lock, ShieldCheck, FileText, Wrench, Inbox, Clock, ShoppingCart, Rocket, Building2, Cloud, Code, HelpCircle, Globe } from 'lucide-vue-next';

/*
  All marketing copy lives here so it is easy to edit and, later, to replace
  with API or CMS data. Keep claims limited to what the product really does.
*/

// Reuse your existing auth routes. Change these two paths if yours differ.
export const AUTH = {
    login: '/login',
    register: '/register',
    registerWithPlan: (plan) => `/register?plan=${plan}`
};

export const navLinks = [
    { label: 'Features', to: '/features' },
    { label: 'Solutions', to: '/#solutions' },
    { label: 'Pricing', to: '/pricing' },
    { label: 'Security', to: '/#security' }
];

export const resourceLinks = [
    { label: 'FAQ', description: 'Answers to common questions', to: '/#faq' },
    { label: 'About', description: 'The product and the team', to: '/about' },
    { label: 'Contact', description: 'Talk to us or request a demo', to: '/contact' }
];

export const capabilities = [
    { icon: Layers, label: 'Separate workspaces' },
    { icon: KeyRound, label: 'Role-based access' },
    { icon: Users, label: 'Human handoff' },
    { icon: BookOpen, label: 'Knowledge-based answers' }
];

export const problems = [
    { icon: Inbox, title: 'Too many conversations', body: 'Customer questions arrive from every direction and nothing sits in one place.' },
    { icon: Clock, title: 'Slow response times', body: 'Agents spend hours answering the same questions while harder ones wait.' },
    { icon: BookOpen, title: 'Lost knowledge', body: 'Important support information is scattered across docs, chats and people.' }
];

export const features = [
    { id: 'ai', icon: Sparkles, title: 'AI-assisted replies', body: 'Answer common questions instantly and keep agents focused on the complex ones.', link: 'Explore AI', to: '/features#ai' },
    { id: 'conversations', icon: MessageSquare, title: 'Unified conversations', body: 'Every customer conversation in a shared inbox with full history.', link: 'Explore conversations', to: '/features#conversations' },
    { id: 'tickets', icon: Ticket, title: 'Ticket management', body: 'Organize, prioritize and resolve issues from first message to close.', link: 'Explore tickets', to: '/features#tickets' },
    { id: 'knowledge-base', icon: BookOpen, title: 'Knowledge base', body: 'Build a trusted source of answers that your AI and your team share.', link: 'Explore knowledge base', to: '/features#knowledge-base' },
    { id: 'team', icon: Users, title: 'Team roles and permissions', body: 'Invite teammates and decide what each person can see and change.', link: 'Explore team tools', to: '/features#team' },
    { id: 'analytics', icon: BarChart3, title: 'Analytics and reporting', body: 'See volume, AI resolution, response time and ticket progress.', link: 'Explore analytics', to: '/features#analytics' }
];

export const featureDetails = [
    {
        id: 'ai',
        icon: Sparkles,
        title: 'AI that answers with your knowledge',
        body: 'The AI assistant replies to customers using the content in your knowledge base, and hands the conversation to a person when it should not answer alone.',
        points: ['Answers common questions instantly', 'Hands off to a human agent when needed', 'Agents see the full conversation history']
    },
    {
        id: 'conversations',
        icon: MessageSquare,
        title: 'Every conversation in one place',
        body: 'Give your support team one shared inbox for customer conversations instead of scattered channels.',
        points: ['Filter by status and assignee', 'Assign conversations to teammates', 'Leave internal notes customers never see']
    },
    {
        id: 'tickets',
        icon: Ticket,
        title: 'Tickets from first message to close',
        body: 'Turn conversations into tickets, set priorities and keep track of what is still open.',
        points: ['Create and prioritize tickets', 'Track status as work progresses', 'Keep the conversation attached to the ticket']
    },
    {
        id: 'knowledge-base',
        icon: BookOpen,
        title: 'Teach your AI how your business works',
        body: 'Add your products, policies, FAQs and documentation so answers stay consistent.',
        points: ['One source of answers for AI and agents', 'Update content as your business changes', 'Control what the AI can draw on']
    },
    {
        id: 'team',
        icon: Users,
        title: 'Roles and permissions for your team',
        body: 'Invite your team to the workspace and give each person the access their role needs.',
        points: ['Invite teammates to your workspace', 'Assign a role to each person', 'Keep each workspace separate from others']
    },
    {
        id: 'analytics',
        icon: BarChart3,
        title: 'Understand your support operation',
        body: 'Follow how conversations and tickets move so you can plan staffing and improve answers.',
        points: ['Conversation volume over time', 'How much the AI resolves', 'Response time and ticket progress']
    },
    {
        id: 'widget',
        icon: Globe,
        title: 'A chat widget for your website',
        body: 'Add one script to your site and visitors can start a conversation with your AI and your team.',
        points: ['One script tag to install', 'AI answers first, agents join when needed', 'Works alongside your existing site']
    }
];

export const kbSources = [
    { icon: ShoppingCart, label: 'Products' },
    { icon: FileText, label: 'Policies' },
    { icon: HelpCircle, label: 'FAQs' },
    { icon: Code, label: 'Documentation' },
    { icon: Lock, label: 'Internal knowledge' }
];

export const inboxRows = [
    { initials: 'PN', color: '#2563EB', name: 'Priya Nair', preview: 'How do I reset my password?', status: 'AI resolved', tone: 'ai' },
    { initials: 'TB', color: '#B45309', name: 'Tom Becker', preview: 'I was charged twice this month', status: 'Assigned', tone: 'warn' },
    { initials: 'LO', color: '#0F766E', name: 'Lena Ortiz', preview: 'Can I add a teammate to my plan?', status: 'Resolved', tone: 'ok' },
    { initials: 'SO', color: '#7C3AED', name: 'Sam Okoye', preview: 'Where do I find the API key page?', status: 'Open', tone: 'blue' }
];

export const steps = [
    { title: 'Set up your support workspace', body: 'Create your workspace and invite your team with the right roles.' },
    { title: 'Configure your knowledge base and AI', body: 'Add your help content, then choose how the AI should answer.' },
    { title: 'Manage conversations and escalate', body: 'Watch conversations arrive and hand off to a human agent when needed.' }
];

export const analyticsTiles = [
    { label: 'Conversations', value: '1,284' },
    { label: 'Resolved by AI', value: '61%' },
    { label: 'Median first reply', value: '0:42' },
    { label: 'Open tickets', value: '37' }
];

export const useCases = [
    { icon: Cloud, title: 'SaaS companies', body: 'Automate repetitive product questions and support your users around the clock.' },
    { icon: ShoppingCart, title: 'E-commerce', body: 'Answer order, product and shipping questions faster with your own policies.' },
    { icon: Rocket, title: 'Startups', body: 'Scale customer support without scaling your team at the same pace.' },
    { icon: Building2, title: 'Growing businesses', body: 'Give agents the tools they need as support volume increases.' }
];

export const securityItems = [
    { icon: Layers, title: 'Workspace isolation', body: 'Each workspace keeps its customers, conversations and knowledge separate.' },
    { icon: KeyRound, title: 'Role-based access', body: 'Give every teammate only the access their role needs.' },
    { icon: Lock, title: 'Secure authentication', body: 'Team members sign in with their own accounts.' },
    { icon: ShieldCheck, title: 'API protection', body: 'Requests to the platform are authenticated and validated.' },
    { icon: FileText, title: 'Audit logging', body: 'Review important account and workspace activity.' },
    { icon: Wrench, title: 'Data controls', body: 'Decide what knowledge your AI can use to answer customers.' }
];

/*
  Prices and limits below come from the design reference. Before launch, load
  them from your billing source of truth (GET /api/v1/public/plans) instead of
  hard-coding them here.
*/
export const plans = [
    { id: 'starter', name: 'Starter', blurb: 'Perfect for small businesses', price: '$29', per: '/month', featured: false, cta: 'Get started', items: ['Up to 1,000 conversations', '1 team member', 'Basic analytics', 'Email support'] },
    { id: 'pro', name: 'Professional', blurb: 'For growing businesses', price: '$79', per: '/month', featured: true, cta: 'Start free trial', items: ['Up to 10,000 conversations', 'Up to 10 team members', 'Advanced analytics', 'Priority support'] },
    {
        id: 'enterprise',
        name: 'Enterprise',
        blurb: 'For large organizations',
        price: 'Custom',
        per: '',
        featured: false,
        cta: 'Contact sales',
        items: ['Unlimited conversations', 'Advanced security and SSO', 'Dedicated account manager', 'Custom integrations']
    }
];

export const faqs = [
    { q: 'What is AI customer support?', a: 'SupportAI answers customer questions in your chat widget using the knowledge you give it, and hands the conversation to a human agent when it needs one.' },
    { q: 'How does the AI learn about my business?', a: 'You add your products, policies and help content to the knowledge base. The AI uses that content to answer, so replies stay consistent with how you work.' },
    { q: 'Can human agents take over a conversation?', a: 'Yes. Agents can take over at any point and see the full conversation history, and they can leave internal notes that customers never see.' },
    { q: 'Can I install the widget on my website?', a: 'Yes. You add a single script tag to your site and the chat widget appears for your visitors.' },
    { q: 'Does it support multiple team members and roles?', a: 'Yes. Invite your team to the workspace and give each person a role that controls what they can see and change.' },
    { q: 'Is my data separate from other workspaces?', a: 'Each workspace keeps its own customers, conversations and knowledge separate from every other workspace.' },
    { q: 'How does billing work, and can I cancel?', a: 'Plans are billed monthly. You can change plans or cancel from your billing settings.' }
];

export const footerColumns = [
    {
        title: 'Product',
        links: [
            { label: 'Features', to: '/features' },
            { label: 'Pricing', to: '/pricing' },
            { label: 'Analytics', to: '/features#analytics' },
            { label: 'Chat widget', to: '/features#widget' }
        ]
    },
    {
        title: 'Solutions',
        links: [
            { label: 'SaaS', to: '/#solutions' },
            { label: 'E-commerce', to: '/#solutions' },
            { label: 'Startups', to: '/#solutions' },
            { label: 'Growing teams', to: '/#solutions' }
        ]
    },
    {
        title: 'Company',
        links: [
            { label: 'About', to: '/about' },
            { label: 'Contact', to: '/contact' },
            { label: 'Security', to: '/#security' },
            { label: 'FAQ', to: '/#faq' }
        ]
    },
    {
        title: 'Legal',
        links: [
            { label: 'Privacy', to: '/privacy' },
            { label: 'Terms', to: '/terms' }
        ]
    }
];
