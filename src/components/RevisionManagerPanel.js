/**
 * Revision Manager Panel Component
 *
 * Main component for the sidebar panel with status controls and timeline.
 */

import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
    PanelBody,
    RadioControl,
    Spinner,
    Notice,
    Button
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

import RevisionTimeline from './RevisionTimeline';

const RevisionManagerPanel = () => {
    const [loading, setLoading] = useState(false);
    const [revisionData, setRevisionData] = useState(null);
    const [revisionMode, setRevisionMode] = useState('open');
    const [error, setError] = useState(null);

    const postId = useSelect((select) => {
        return select('core/editor').getCurrentPostId();
    }, []);

    const postType = useSelect((select) => {
        return select('core/editor').getCurrentPostType();
    }, []);

    const postUrl = useSelect((select) => {
        const post = select('core/editor').getCurrentPost();
        return post?.link || '';
    }, []);

    const { createNotice, removeNotice } = useDispatch('core/notices');

    // Load revision data on component mount and when postId changes
    useEffect(() => {
        if (postId) {
            loadRevisionData();
        }
    }, [postId]);

    // Check for content mismatch and show notice
    useEffect(() => {
        const noticeId = 'dgw-content-mismatch';

        // Always remove existing notice first
        removeNotice(noticeId);

        // Only show notice in pending mode with revision data
        if (revisionMode === 'pending' && revisionData && revisionData.timeline && revisionData.timeline.length > 0) {
            const currentRevision = revisionData.timeline.find(r => r.is_current);
            const latestRevision = revisionData.timeline[0]; // First in DESC order

            // Show notice if current revision differs from latest revision
            if (currentRevision && latestRevision && currentRevision.id !== latestRevision.id) {
                createNotice(
                    'warning',
                    __('You are editing content that differs from what visitors see. Latest changes are not yet published.', 'dgwltd-revision-manager'),
                    {
                        id: noticeId,
                        isDismissible: true,
                        actions: [
                            {
                                label: __('View Published Version', 'dgwltd-revision-manager'),
                                onClick: () => window.open(postUrl, '_blank')
                            },
                            {
                                label: __('Publish Latest Changes', 'dgwltd-revision-manager'),
                                onClick: () => handleSetLatestAsCurrent()
                            }
                        ]
                    }
                );
            }
        }
    }, [revisionMode, revisionData, postUrl, createNotice, removeNotice]);

    const loadRevisionData = async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiFetch({
                path: `/dgw-revision-manager/v1/revisions/${postId}`,
            });

            setRevisionData(response);
            setRevisionMode(response.revision_mode || 'open');
        } catch (err) {
            setError(__('Failed to load revision data.', 'dgwltd-revision-manager'));
            console.error('Error loading revision data:', err);
        } finally {
            setLoading(false);
        }
    };

    const handleRevisionModeChange = async (newMode) => {
        setLoading(true);
        setError(null);

        try {
            await apiFetch({
                path: `/dgw-revision-manager/v1/revisions/${postId}/mode`,
                method: 'PUT',
                data: {
                    mode: newMode
                }
            });

            setRevisionMode(newMode);

            // Reload revision data to get updated timeline
            await loadRevisionData();
        } catch (err) {
            setError(__('Failed to update revision mode.', 'dgwltd-revision-manager'));
            console.error('Error updating revision mode:', err);
        } finally {
            setLoading(false);
        }
    };

    const handleSetLatestAsCurrent = async () => {
        if (!revisionData || !revisionData.timeline || revisionData.timeline.length === 0) {
            return;
        }

        const latestRevision = revisionData.timeline[0];
        setLoading(true);
        setError(null);

        try {
            const response = await apiFetch({
                path: `/dgw-revision-manager/v1/revisions/${latestRevision.id}/set-current`,
                method: 'POST'
            });

            if (response.success) {
                // Remove the notice since we've resolved the mismatch
                removeNotice('dgw-content-mismatch');

                // Reload revision data to get updated timeline
                await loadRevisionData();
            } else {
                setError(__('Failed to publish latest changes.', 'dgwltd-revision-manager'));
            }
        } catch (err) {
            setError(__('Error publishing latest changes.', 'dgwltd-revision-manager'));
            console.error('Error setting latest as current:', err);
        } finally {
            setLoading(false);
        }
    };


    const handleRevisionChange = () => {
        // Reload data when timeline changes
        loadRevisionData();
    };

    if (!postId) {
        return (
            <PanelBody title={__('Revision Manager', 'dgwltd-revision-manager')}>
                <p>{__('Save the post to enable revision management.', 'dgwltd-revision-manager')}</p>
            </PanelBody>
        );
    }

    return (
        <div className="dgw-revision-manager-panel">
            {error && (
                <Notice status="error" isDismissible onRemove={() => setError(null)}>
                    {error}
                </Notice>
            )}

            <PanelBody title={__('Revision Manager', 'dgwltd-revision-manager')} initialOpen={true}>
                <RadioControl
                    label={__('Revision Mode', 'dgwltd-revision-manager')}
                    selected={revisionMode}
                    onChange={handleRevisionModeChange}
                    disabled={loading}
                    options={[
                        {
                            label: __('Standard WordPress Revisions', 'dgwltd-revision-manager'),
                            value: 'open'
                        },
                        {
                            label: __('Requires Approval', 'dgwltd-revision-manager'),
                            value: 'pending'
                        }
                    ]}
                    help={__('Choose how revisions are managed for this post.', 'dgwltd-revision-manager')}
                />

                {revisionMode === 'open' && (
                    <p className="description">
                        {__('All changes are saved automatically as revisions using standard WordPress behavior.', 'dgwltd-revision-manager')}
                    </p>
                )}



                {loading && <Spinner />}
            </PanelBody>

            {revisionMode === 'pending' && (
                <PanelBody title={__('Revision Timeline', 'dgwltd-revision-manager')} initialOpen={true}>
                    {loading ? (
                        <div className="dgw-loading-container">
                            <Spinner />
                            <p>{__('Loading timeline...', 'dgwltd-revision-manager')}</p>
                        </div>
                    ) : revisionData ? (
                        <RevisionTimeline
                            revisionData={revisionData}
                            onRevisionChange={handleRevisionChange}
                        />
                    ) : (
                        <p>{__('No revision data available.', 'dgwltd-revision-manager')}</p>
                    )}

                    <div className="dgw-panel-actions">
                        <Button
                            isSecondary
                            onClick={loadRevisionData}
                            disabled={loading}
                        >
                            {__('Refresh', 'dgwltd-revision-manager')}
                        </Button>
                    </div>
                </PanelBody>
            )}
        </div>
    );
};

export default RevisionManagerPanel;