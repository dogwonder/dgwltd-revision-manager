/**
 * Revision Manager Panel Component
 *
 * Main component for the sidebar panel with status controls and timeline.
 */

import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
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

    // Load revision data on component mount and when postId changes
    useEffect(() => {
        if (postId) {
            loadRevisionData();
        }
    }, [postId]);

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