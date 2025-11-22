<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { linkedin as linkedinAuth } from '@/routes/auth';
import linkedin from '@/routes/linkedin';
import { edit as passwordEdit } from '@/routes/password';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Linkedin } from 'lucide-vue-next';
import { ref } from 'vue';

interface LinkedInAccountData {
    name: string;
    email: string;
    avatar: string | null;
    connected_at: string;
}

interface Props {
    linkedInAccount: LinkedInAccountData | null;
    hasPassword: boolean;
}

defineProps<Props>();

const showDisconnectDialog = ref(false);
const isDisconnecting = ref(false);

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'LinkedIn settings',
        href: linkedin.settings().url,
    },
];

function connectLinkedIn() {
    window.location.href = linkedinAuth().url;
}

function confirmDisconnect() {
    showDisconnectDialog.value = true;
}

function disconnect() {
    isDisconnecting.value = true;
    router.delete(linkedin.destroy().url, {
        preserveScroll: true,
        onFinish: () => {
            isDisconnecting.value = false;
            showDisconnectDialog.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="LinkedIn settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="LinkedIn account"
                    description="Connect your LinkedIn account to enable social login"
                />

                <!-- Not Connected State -->
                <div v-if="!linkedInAccount" class="space-y-4">
                    <div
                        class="rounded-lg border border-border bg-card p-6 text-center"
                    >
                        <div
                            class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10"
                        >
                            <Linkedin class="h-6 w-6 text-primary" />
                        </div>
                        <h3 class="mb-2 text-lg font-semibold">
                            Connect LinkedIn
                        </h3>
                        <p class="mb-4 text-sm text-muted-foreground">
                            Link your LinkedIn account to enable quick login
                            with LinkedIn in addition to your email and
                            password.
                        </p>
                        <Button @click="connectLinkedIn">
                            <Linkedin class="mr-2 h-4 w-4" />
                            Connect LinkedIn account
                        </Button>
                    </div>
                </div>

                <!-- Connected State -->
                <div v-else class="space-y-4">
                    <div
                        class="flex items-start justify-between rounded-lg border border-border bg-card p-6"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                v-if="linkedInAccount.avatar"
                                class="h-12 w-12 flex-shrink-0 overflow-hidden rounded-full"
                            >
                                <img
                                    :src="linkedInAccount.avatar"
                                    :alt="linkedInAccount.name"
                                    class="h-full w-full object-cover"
                                />
                            </div>
                            <div
                                v-else
                                class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-primary/10"
                            >
                                <Linkedin class="h-6 w-6 text-primary" />
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold">
                                    {{ linkedInAccount.name }}
                                </h3>
                                <p class="text-sm text-muted-foreground">
                                    {{ linkedInAccount.email }}
                                </p>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Connected on
                                    {{ linkedInAccount.connected_at }}
                                </p>
                            </div>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="confirmDisconnect"
                        >
                            Disconnect
                        </Button>
                    </div>

                    <div
                        class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950"
                    >
                        <p class="text-sm text-green-800 dark:text-green-200">
                            Your LinkedIn account is connected. You can log in
                            using either your email and password or LinkedIn.
                        </p>
                    </div>
                </div>
            </div>
        </SettingsLayout>

        <!-- Disconnect Confirmation Dialog -->
        <Dialog v-model:open="showDisconnectDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Disconnect LinkedIn account?</DialogTitle>
                    <DialogDescription>
                        <span v-if="!hasPassword" class="text-destructive">
                            You must set a password before disconnecting
                            LinkedIn. Please visit the Password settings page
                            first.
                        </span>
                        <span v-else>
                            Your LinkedIn account will be disconnected. You'll
                            still be able to log in with your email and
                            password. You can reconnect LinkedIn anytime.
                        </span>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showDisconnectDialog = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        v-if="hasPassword"
                        variant="destructive"
                        :disabled="isDisconnecting"
                        @click="disconnect"
                    >
                        Disconnect
                    </Button>
                    <Button
                        v-else
                        as-child
                        @click="showDisconnectDialog = false"
                    >
                        <a :href="passwordEdit().url">
                            Go to Password Settings
                        </a>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
