<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { toast } from 'vue3-toastify';
import profileService from '@/services/profileService';

const auth = useAuthStore();

const loading = ref(true);
const saving = ref(false);
const uploading = ref(false);
const avatarInput = ref(null);
const errors = ref({});

const form = reactive({
    first_name: '',
    last_name: '',
    username: '',
    phone: '',
    company_name: '',
    timezone: 'UTC',
    language: 'en'
});

const meta = reactive({
    email: '',
    is_active: true,
    email_verified_at: null,
    last_login_at: null,
    created_at: null
});

const avatarUrl = ref(null);

const scope = computed(() => auth.scope || 'tenant');
const scopeLabel = computed(() => (scope.value === 'platform' ? 'Platform' : 'Workspace'));

const roleLabel = computed(() => {
    const role = auth.role || '';
    return role
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
});

const fullName = computed(() => {
    const name = `${form.first_name} ${form.last_name}`.trim();
    return name || form.username || meta.email || 'User';
});

const initial = computed(() => (form.first_name || form.username || meta.email || 'U').charAt(0).toUpperCase());

const isVerified = computed(() => !!meta.email_verified_at);

const permissionCount = computed(() => auth.permissions?.length || 0);

const timezones = [
    { label: 'UTC', value: 'UTC' },
    { label: 'Asia/Dhaka', value: 'Asia/Dhaka' },
    { label: 'Asia/Kolkata', value: 'Asia/Kolkata' },
    { label: 'America/New_York', value: 'America/New_York' },
    { label: 'America/Los_Angeles', value: 'America/Los_Angeles' },
    { label: 'Europe/London', value: 'Europe/London' },
    { label: 'Europe/Paris', value: 'Europe/Paris' },
    { label: 'Australia/Sydney', value: 'Australia/Sydney' },
    { label: 'Asia/Tokyo', value: 'Asia/Tokyo' },
    { label: 'Asia/Singapore', value: 'Asia/Singapore' }
];

const languages = [
    { label: 'English', value: 'en' },
    { label: 'বাংলা (Bengali)', value: 'bn' },
    { label: 'Español', value: 'es' },
    { label: 'Français', value: 'fr' },
    { label: 'Deutsch', value: 'de' },
    { label: '日本語', value: 'ja' },
    { label: '中文', value: 'zh' }
];

const formatDate = (value, withTime = false) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return withTime ? date.toLocaleString() : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

const applyProfile = (profile) => {
    form.first_name = profile.first_name || '';
    form.last_name = profile.last_name || '';
    form.username = profile.username || '';
    form.phone = profile.phone || '';
    form.company_name = profile.company_name || '';
    form.timezone = profile.timezone || 'UTC';
    form.language = profile.language || 'en';
    avatarUrl.value = profile.avatar_url || null;

    meta.email = profile.email || auth.user?.email || '';
    meta.is_active = profile.is_active ?? true;
    meta.email_verified_at = profile.email_verified_at || null;
    meta.last_login_at = profile.last_login_at || null;
    meta.created_at = profile.created_at || null;
};

const loadProfile = async () => {
    loading.value = true;
    try {
        const { data } = await profileService.getProfile();
        if (data.success) {
            applyProfile(data.data);
        }
    } catch (error) {
        console.error('Failed to load profile:', error);
        if (auth.user) applyProfile(auth.user);
    } finally {
        loading.value = false;
    }
};

const handleSubmit = async () => {
    saving.value = true;
    errors.value = {};
    try {
        const { data } = await profileService.updateProfile({
            first_name: form.first_name,
            last_name: form.last_name,
            username: form.username,
            phone: form.phone,
            company_name: form.company_name,
            timezone: form.timezone,
            language: form.language
        });

        if (data.success) {
            applyProfile({ ...data.data, email: meta.email });
            await auth.fetchMe().catch(() => {});
            toast.success('Profile updated successfully');
        }
    } catch (error) {
        errors.value = error.response?.data?.errors || {};
        if (!Object.keys(errors.value).length) {
            toast.error(error.response?.data?.message || 'Failed to update profile');
        }
    } finally {
        saving.value = false;
    }
};

const triggerAvatarUpload = () => avatarInput.value?.click();

const handleAvatarChange = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    uploading.value = true;
    try {
        const { data } = await profileService.updateAvatar(file);
        if (data.success) {
            avatarUrl.value = data.data.avatar_url;
            await auth.fetchMe().catch(() => {});
            toast.success('Avatar updated successfully');
        }
    } catch (error) {
        toast.error(error.response?.data?.message || 'Failed to update avatar');
    } finally {
        uploading.value = false;
        if (avatarInput.value) avatarInput.value.value = '';
    }
};

const fieldError = (field) => errors.value[field]?.[0];

onMounted(loadProfile);
</script>

<template>
    <div class="profile-page p-4 md:p-6">
        <!-- Loading -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-24 gap-3">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
            <span class="text-surface-500 dark:text-surface-400">Loading your profile…</span>
        </div>

        <template v-else>
            <!-- ============ HERO ============ -->
            <section class="hero">
                <div class="hero-glow"></div>
                <div class="relative z-10 flex flex-col md:flex-row md:items-center gap-6">
                    <!-- Avatar -->
                    <div class="relative shrink-0">
                        <Avatar :image="avatarUrl || undefined" :label="avatarUrl ? undefined : initial" shape="circle" class="hero-avatar" @error="avatarUrl = null" />
                        <button type="button" class="avatar-edit" :disabled="uploading" title="Change avatar" @click="triggerAvatarUpload">
                            <i :class="uploading ? 'pi pi-spin pi-spinner' : 'pi pi-camera'"></i>
                        </button>
                        <input type="file" ref="avatarInput" accept="image/*" class="hidden" @change="handleAvatarChange" />
                    </div>

                    <!-- Identity -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="text-2xl md:text-3xl font-bold text-white truncate">{{ fullName }}</h1>
                            <i v-if="isVerified" class="pi pi-verified text-white/90" v-tooltip.top="'Email verified'"></i>
                        </div>
                        <p class="text-white/80 mt-1 truncate">{{ meta.email }}</p>

                        <div class="flex flex-wrap gap-2 mt-4">
                            <span class="hero-chip"><i class="pi pi-id-card"></i>{{ roleLabel || 'Member' }}</span>
                            <span class="hero-chip"><i class="pi pi-building"></i>{{ scopeLabel }} scope</span>
                            <span class="hero-chip" :class="meta.is_active ? 'hero-chip-success' : 'hero-chip-danger'">
                                <i :class="meta.is_active ? 'pi pi-check-circle' : 'pi pi-ban'"></i>
                                {{ meta.is_active ? 'Active' : 'Suspended' }}
                            </span>
                        </div>
                    </div>

                    <!-- Quick facts -->
                    <div class="hidden lg:flex gap-3">
                        <div class="hero-fact">
                            <i class="pi pi-sign-in"></i>
                            <div>
                                <span>Last login</span>
                                <strong>{{ formatDate(meta.last_login_at, true) }}</strong>
                            </div>
                        </div>
                        <div class="hero-fact">
                            <i class="pi pi-calendar-plus"></i>
                            <div>
                                <span>Member since</span>
                                <strong>{{ formatDate(meta.created_at) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============ BODY ============ -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
                <!-- Main column -->
                <div class="lg:col-span-2 space-y-6">
                    <form @submit.prevent="handleSubmit" class="space-y-6">
                        <!-- Personal Information -->
                        <section class="panel">
                            <header class="panel-head">
                                <span class="panel-icon"><i class="pi pi-user-edit"></i></span>
                                <div>
                                    <h2>Personal Information</h2>
                                    <p>How you appear across the workspace</p>
                                </div>
                            </header>
                            <div class="panel-body grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="field-label"><i class="pi pi-user"></i>First Name</label>
                                    <InputText v-model="form.first_name" class="w-full" :class="{ 'p-invalid': fieldError('first_name') }" placeholder="First name" />
                                    <small v-if="fieldError('first_name')" class="field-error">{{ fieldError('first_name') }}</small>
                                </div>
                                <div>
                                    <label class="field-label"><i class="pi pi-user"></i>Last Name</label>
                                    <InputText v-model="form.last_name" class="w-full" :class="{ 'p-invalid': fieldError('last_name') }" placeholder="Last name" />
                                    <small v-if="fieldError('last_name')" class="field-error">{{ fieldError('last_name') }}</small>
                                </div>
                                <div>
                                    <label class="field-label"><i class="pi pi-at"></i>Username</label>
                                    <InputText v-model="form.username" class="w-full" :class="{ 'p-invalid': fieldError('username') }" placeholder="Username" />
                                    <small v-if="fieldError('username')" class="field-error">{{ fieldError('username') }}</small>
                                </div>
                                <div>
                                    <label class="field-label"><i class="pi pi-envelope"></i>Email Address</label>
                                    <InputText :modelValue="meta.email" class="w-full" disabled />
                                    <small class="field-hint"><i class="pi pi-lock"></i>Contact a Super Admin to change your email</small>
                                </div>
                                <div>
                                    <label class="field-label"><i class="pi pi-phone"></i>Phone</label>
                                    <InputText v-model="form.phone" type="tel" class="w-full" :class="{ 'p-invalid': fieldError('phone') }" placeholder="+1 234 567 8900" />
                                    <small v-if="fieldError('phone')" class="field-error">{{ fieldError('phone') }}</small>
                                </div>
                                <div>
                                    <label class="field-label"><i class="pi pi-briefcase"></i>Company Name</label>
                                    <InputText v-model="form.company_name" class="w-full" :class="{ 'p-invalid': fieldError('company_name') }" placeholder="Company name" />
                                    <small v-if="fieldError('company_name')" class="field-error">{{ fieldError('company_name') }}</small>
                                </div>
                            </div>
                        </section>

                        <!-- Preferences -->
                        <section class="panel">
                            <header class="panel-head">
                                <span class="panel-icon"><i class="pi pi-sliders-h"></i></span>
                                <div>
                                    <h2>Preferences</h2>
                                    <p>Personalize your locale and language</p>
                                </div>
                            </header>
                            <div class="panel-body grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="field-label"><i class="pi pi-clock"></i>Timezone</label>
                                    <Select v-model="form.timezone" :options="timezones" optionLabel="label" optionValue="value" placeholder="Select Timezone" class="w-full" :class="{ 'p-invalid': fieldError('timezone') }" />
                                    <small v-if="fieldError('timezone')" class="field-error">{{ fieldError('timezone') }}</small>
                                </div>
                                <div>
                                    <label class="field-label"><i class="pi pi-globe"></i>Language</label>
                                    <Select v-model="form.language" :options="languages" optionLabel="label" optionValue="value" placeholder="Select Language" class="w-full" :class="{ 'p-invalid': fieldError('language') }" />
                                    <small v-if="fieldError('language')" class="field-error">{{ fieldError('language') }}</small>
                                </div>
                            </div>
                        </section>

                        <!-- Actions -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 panel-actions">
                            <span class="field-hint"><i class="pi pi-info-circle"></i>Your changes are saved to your account only</span>
                            <div class="flex gap-3">
                                <Button type="button" label="Reset" severity="secondary" outlined icon="pi pi-refresh" @click="loadProfile" />
                                <Button type="submit" icon="pi pi-save" :label="saving ? 'Saving…' : 'Save Changes'" :loading="saving" :disabled="saving" />
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Account overview -->
                    <section class="panel">
                        <header class="panel-head">
                            <span class="panel-icon"><i class="pi pi-chart-simple"></i></span>
                            <div>
                                <h2>Account Overview</h2>
                                <p>Snapshot of your account</p>
                            </div>
                        </header>
                        <div class="panel-body space-y-1">
                            <div class="overview-row">
                                <span><i class="pi pi-envelope"></i>Email</span>
                                <strong class="truncate max-w-[60%]" :title="meta.email">{{ meta.email || '—' }}</strong>
                            </div>
                            <div class="overview-row">
                                <span><i class="pi pi-phone"></i>Phone</span>
                                <strong>{{ form.phone || '—' }}</strong>
                            </div>
                            <div class="overview-row">
                                <span><i class="pi pi-briefcase"></i>Company</span>
                                <strong class="truncate max-w-[60%]">{{ form.company_name || '—' }}</strong>
                            </div>
                            <div class="overview-row">
                                <span><i class="pi pi-clock"></i>Timezone</span>
                                <strong>{{ form.timezone || '—' }}</strong>
                            </div>
                            <div class="overview-row">
                                <span><i class="pi pi-globe"></i>Language</span>
                                <strong>{{ form.language?.toUpperCase() || '—' }}</strong>
                            </div>
                            <div class="overview-row">
                                <span><i class="pi pi-calendar-plus"></i>Member since</span>
                                <strong>{{ formatDate(meta.created_at) }}</strong>
                            </div>
                        </div>
                    </section>

                    <!-- Access -->
                    <section class="panel">
                        <header class="panel-head">
                            <span class="panel-icon"><i class="pi pi-shield"></i></span>
                            <div>
                                <h2>Access &amp; Permissions</h2>
                                <p>{{ roleLabel || 'Member' }} · {{ permissionCount }} permissions</p>
                            </div>
                        </header>
                        <div class="panel-body space-y-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm text-surface-600 dark:text-surface-400">Role</span>
                                <Tag :value="roleLabel || 'Member'" icon="pi pi-id-card" severity="info" />
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm text-surface-600 dark:text-surface-400">Scope</span>
                                <Tag :value="scopeLabel" icon="pi pi-building" severity="secondary" />
                            </div>
                            <Divider v-if="permissionCount" />
                            <div v-if="permissionCount" class="flex flex-wrap gap-2 max-h-56 overflow-auto pr-1">
                                <Tag v-for="p in auth.permissions" :key="p" :value="p" severity="secondary" class="!text-[11px]" />
                            </div>
                            <p v-else class="text-sm text-surface-500 dark:text-surface-400">No additional permissions assigned.</p>
                        </div>
                    </section>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
/* ================= HERO ================= */
.hero {
    position: relative;
    overflow: hidden;
    border-radius: 1.25rem;
    padding: 1.75rem;
    color: #fff;
    background-color: var(--primary-color);
    background-image: radial-gradient(circle at 88% 12%, rgba(255, 255, 255, 0.35), transparent 42%), radial-gradient(circle at 8% 125%, rgba(0, 0, 0, 0.4), transparent 55%);
    box-shadow: 0 24px 45px -25px rgba(0, 0, 0, 0.55);
}

.hero-glow {
    position: absolute;
    top: -40%;
    right: -10%;
    width: 22rem;
    height: 22rem;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.25), transparent 65%);
    pointer-events: none;
}

.hero-avatar {
    width: 6rem !important;
    height: 6rem !important;
    font-size: 2.25rem !important;
    border: 4px solid rgba(255, 255, 255, 0.9);
    box-shadow: 0 12px 28px -12px rgba(0, 0, 0, 0.6);
    background: rgba(255, 255, 255, 0.18);
    color: #fff;
    backdrop-filter: blur(2px);
}

.avatar-edit {
    position: absolute;
    right: -2px;
    bottom: -2px;
    width: 2.35rem;
    height: 2.35rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 2px solid #fff;
    background: var(--surface-card, #fff);
    color: var(--primary-color);
    cursor: pointer;
    box-shadow: 0 6px 16px -6px rgba(0, 0, 0, 0.5);
    transition:
        transform 0.15s ease,
        box-shadow 0.15s ease;
}

.avatar-edit:hover:not(:disabled) {
    transform: translateY(-1px) scale(1.05);
}

.avatar-edit:disabled {
    opacity: 0.75;
    cursor: progress;
}

.hero-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.7rem;
    border-radius: 9999px;
    font-size: 0.78rem;
    font-weight: 600;
    color: #fff;
    background: rgba(255, 255, 255, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(6px);
}

.hero-chip-success {
    background: rgba(34, 197, 94, 0.28);
    border-color: rgba(34, 197, 94, 0.5);
}

.hero-chip-danger {
    background: rgba(239, 68, 68, 0.28);
    border-color: rgba(239, 68, 68, 0.5);
}

.hero-fact {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.65rem 0.9rem;
    border-radius: 0.9rem;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.22);
    backdrop-filter: blur(6px);
    min-width: 9.5rem;
}

.hero-fact i {
    font-size: 1.1rem;
    opacity: 0.9;
}

.hero-fact span {
    display: block;
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    opacity: 0.75;
}

.hero-fact strong {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
}

/* ================= PANELS ================= */
.panel {
    border-radius: 1rem;
    border: 1px solid var(--surface-border, #e5e7eb);
    background: var(--surface-card, #fff);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    transition:
        box-shadow 0.2s ease,
        transform 0.2s ease;
}

.panel:hover {
    box-shadow: 0 18px 35px -28px rgba(0, 0, 0, 0.55);
}

.panel-head {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1.15rem 1.35rem;
    border-bottom: 1px solid var(--surface-border, #e5e7eb);
}

.panel-head h2 {
    font-size: 1.02rem;
    font-weight: 700;
    line-height: 1.2;
    color: var(--text-color, #0f172a);
}

.panel-head p {
    font-size: 0.8rem;
    color: var(--text-color-secondary, #64748b);
    margin-top: 0.1rem;
}

.panel-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.6rem;
    height: 2.6rem;
    border-radius: 0.8rem;
    font-size: 1.1rem;
    color: var(--primary-color);
    background: rgba(99, 102, 241, 0.12);
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
}

.panel-body {
    padding: 1.35rem;
}

.panel-actions {
    padding: 1rem 1.25rem;
    border-radius: 1rem;
    border: 1px dashed var(--surface-border, #e5e7eb);
    background: var(--surface-card, #fff);
}

.field-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-color-secondary, #475569);
    margin-bottom: 0.4rem;
}

.field-label i {
    font-size: 0.8rem;
    color: var(--primary-color);
}

.field-error {
    display: block;
    margin-top: 0.3rem;
    font-size: 0.78rem;
    color: #ef4444;
}

.field-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.3rem;
    font-size: 0.75rem;
    color: var(--text-color-secondary, #64748b);
}

.overview-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.25rem;
    border-bottom: 1px dashed var(--surface-border, #e5e7eb);
    font-size: 0.85rem;
}

.overview-row:last-child {
    border-bottom: none;
}

.overview-row span {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: var(--text-color-secondary, #64748b);
}

.overview-row span i {
    color: var(--primary-color);
    font-size: 0.82rem;
}

.overview-row strong {
    color: var(--text-color, #0f172a);
    font-weight: 600;
}

/* Widths */
:deep(.p-inputtext),
:deep(.p-select) {
    width: 100%;
}

:deep(.p-select) {
    display: flex;
}
</style>
