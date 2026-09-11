// src/services/streamingService.js

import { EventSourcePolyfill } from 'event-source-polyfill';

class StreamingService {
    /**
     * Stream AI response using EventSource
     */
    streamAIResponse(conversationId, messageId, onMessage, onComplete, onError) {
        const token = localStorage.getItem('auth_token');
        const tenantId = localStorage.getItem('current_tenant_id');
        const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1';

        if (!token || !tenantId) {
            console.error('Missing authentication for streaming');
            if (onError) onError('Authentication required');
            return null;
        }

        const url = `${baseUrl}/ai/stream/${conversationId}/${messageId}?token=${token}&tenant_id=${tenantId}`;

        console.log('🟢 Initializing stream:', url);

        try {
            // Use native EventSource with token in query params
            const eventSource = new EventSource(url);

            eventSource.onopen = () => {
                console.log('🟢 Stream connection opened');
            };

            eventSource.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);

                    switch (data.type) {
                        case 'start':
                            console.log('🟢 Stream started:', data);
                            break;

                        case 'chunk':
                            if (onMessage && data.content) {
                                onMessage(data.content);
                            }
                            break;

                        case 'complete':
                            console.log('🟢 Stream completed:', data);
                            if (onComplete) {
                                onComplete(data);
                            }
                            eventSource.close();
                            break;

                        case 'error':
                            console.error('🟢 Stream error:', data.message);
                            if (onError) {
                                onError(data.message);
                            }
                            eventSource.close();
                            break;

                        default:
                            console.log('🟢 Unknown event type:', data);
                    }
                } catch (error) {
                    console.error('🟢 Error parsing stream data:', error);
                }
            };

            eventSource.onerror = (error) => {
                console.error('🟢 Stream error:', error);

                // EventSource automatically reconnects, but we want to handle errors
                if (eventSource.readyState === EventSource.CLOSED) {
                    console.log('🟢 Stream closed');
                    if (onError) {
                        onError('Stream connection closed');
                    }
                } else {
                    // Try to reconnect or fail
                    if (onError) {
                        onError('Stream connection error');
                    }
                }

                eventSource.close();
            };

            return eventSource;
        } catch (error) {
            console.error('🟢 Failed to initialize stream:', error);
            if (onError) {
                onError('Failed to initialize streaming.');
            }
            return null;
        }
    }

    /**
     * Stream AI response with retry
     */
    streamWithRetry(conversationId, messageId, onMessage, onComplete, onError, maxRetries = 3) {
        let retries = 0;
        let eventSource = null;
        let isCompleted = false;

        const startStream = () => {
            eventSource = this.streamAIResponse(
                conversationId,
                messageId,
                onMessage,
                (data) => {
                    isCompleted = true;
                    if (onComplete) onComplete(data);
                },
                (error) => {
                    if (isCompleted) return; // Already completed, don't retry

                    if (retries < maxRetries) {
                        retries++;
                        console.log(`🟢 Retrying stream (${retries}/${maxRetries})...`);
                        setTimeout(startStream, 1000 * retries);
                    } else {
                        if (onError) onError(error);
                    }
                }
            );
        };

        startStream();

        return {
            close: () => {
                if (eventSource) {
                    eventSource.close();
                }
            },
            abort: () => {
                if (eventSource) {
                    eventSource.close();
                }
            }
        };
    }

    /**
     * Stream with timeout
     */
    streamWithTimeout(conversationId, messageId, onMessage, onComplete, onError, timeout = 60000) {
        let isCompleted = false;
        let timeoutId = null;

        const eventSource = this.streamAIResponse(
            conversationId,
            messageId,
            (chunk) => {
                // Reset timeout on each chunk
                if (timeoutId) {
                    clearTimeout(timeoutId);
                }
                timeoutId = setTimeout(() => {
                    if (!isCompleted && eventSource) {
                        console.warn('🟢 Stream timeout');
                        eventSource.close();
                        if (onError) onError('Stream timeout');
                    }
                }, timeout);

                if (onMessage) onMessage(chunk);
            },
            (data) => {
                isCompleted = true;
                if (timeoutId) clearTimeout(timeoutId);
                if (onComplete) onComplete(data);
            },
            (error) => {
                isCompleted = true;
                if (timeoutId) clearTimeout(timeoutId);
                if (onError) onError(error);
            }
        );

        return {
            close: () => {
                if (timeoutId) clearTimeout(timeoutId);
                if (eventSource) eventSource.close();
            }
        };
    }

    /**
     * Stream using fetch (fallback for environments without EventSource)
     */
    async streamWithFetch(conversationId, messageId, onMessage, onComplete, onError) {
        const token = localStorage.getItem('auth_token');
        const tenantId = localStorage.getItem('current_tenant_id');
        const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1';

        try {
            const response = await fetch(`${baseUrl}/ai/stream/${conversationId}/${messageId}`, {
                headers: {
                    Authorization: `Bearer ${token}`,
                    'X-Tenant-ID': tenantId,
                    Accept: 'text/event-stream'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();

                if (done) {
                    if (onComplete) onComplete({ type: 'complete' });
                    break;
                }

                buffer += decoder.decode(value, { stream: true });

                // Process complete SSE messages
                const lines = buffer.split('\n\n');
                buffer = lines.pop(); // Keep incomplete line

                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        try {
                            const data = JSON.parse(line.substring(6));

                            switch (data.type) {
                                case 'chunk':
                                    if (onMessage && data.content) {
                                        onMessage(data.content);
                                    }
                                    break;
                                case 'complete':
                                    if (onComplete) onComplete(data);
                                    break;
                                case 'error':
                                    if (onError) onError(data.message);
                                    break;
                            }
                        } catch (e) {
                            console.error('Error parsing SSE data:', e);
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Stream fetch error:', error);
            if (onError) onError(error.message);
        }
    }

    /**
     * Stream with abort controller
     */
    // streamWithAbort(conversationId, messageId, onMessage, onComplete, onError) {
    //     const controller = new AbortController();

    //     const stream = this.streamAIResponse(conversationId, messageId, onMessage, onComplete, onError);

    //     return {
    //         close: () => {
    //             controller.abort();
    //             if (stream) {
    //                 stream.close();
    //             }
    //         },
    //         abort: () => {
    //             controller.abort();
    //             if (stream) {
    //                 stream.close();
    //             }
    //         }
    //     };
    // }
}

export default new StreamingService();
