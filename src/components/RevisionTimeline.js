/**
 * Revision Timeline Component
 *
 * Displays a visual timeline of revisions with current, past, and pending states.
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Button,
    Modal,
    Notice,
    Spinner
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

import RevisionItem from './RevisionItem';

const RevisionTimeline = ({ revisionData, onRevisionChange }) => {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [confirmModal, setConfirmModal] = useState(null);

    const { timeline, current_revision_id, total_revisions } = revisionData;

    const handleSetCurrent = async (revisionId) => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiFetch({
                path: `/dgw-revision-manager/v1/revisions/${revisionId}/set-current`,
                method: 'POST'
            });

            if (response.success) {
                // Notify parent component to refresh data
                onRevisionChange();

                // Close modal
                setConfirmModal(null);
            } else {
                setError(__('Failed to set revision as current.', 'dgwltd-revision-manager'));
            }
        } catch (err) {
            setError(__('Error setting revision as current.', 'dgwltd-revision-manager'));
            console.error('Error setting current revision:', err);
        } finally {
            setLoading(false);
        }
    };

    const openConfirmModal = (revision) => {
        setConfirmModal(revision);
    };

    const closeConfirmModal = () => {
        setConfirmModal(null);
    };

    if (!timeline || timeline.length === 0) {
        return (
            <div className="dgw-timeline-empty">
                <p>{__('No revisions found for this post.', 'dgwltd-revision-manager')}</p>
            </div>
        );
    }

    return (
        <div className="dgw-revision-timeline">
            {error && (
                <Notice status="error" isDismissible onRemove={() => setError(null)}>
                    {error}
                </Notice>
            )}

            <div className="dgw-timeline-header">
                <p className="dgw-timeline-meta">
                    {total_revisions} {total_revisions === 1 ? __('revision', 'dgwltd-revision-manager') : __('revisions', 'dgwltd-revision-manager')}
                </p>
            </div>

            <div className="dgw-timeline-container">
                <div className="dgw-timeline-line" />

                {timeline.map((revision, index) => (
                    <RevisionItem
                        key={revision.id}
                        revision={revision}
                        isFirst={index === 0}
                        isLast={index === timeline.length - 1}
                        onSetCurrent={() => openConfirmModal(revision)}
                        disabled={loading}
                    />
                ))}
            </div>

            {confirmModal && (
                <Modal
                    title={__('Set Revision as Current', 'dgwltd-revision-manager')}
                    onRequestClose={closeConfirmModal}
                    className="dgw-confirm-modal"
                >
                    <p>
                        {__('Are you sure you want to set this revision as the current version?', 'dgwltd-revision-manager')}
                    </p>

                    <div className="dgw-modal-revision-preview">
                        <h4>{confirmModal.title || __('(No title)', 'dgwltd-revision-manager')}</h4>
                        <p className="dgw-revision-meta">
                            {__('By', 'dgwltd-revision-manager')} {confirmModal.author} • {new Date(confirmModal.date).toLocaleDateString()}
                        </p>
                        {confirmModal.excerpt && (
                            <p className="dgw-revision-excerpt">{confirmModal.excerpt}</p>
                        )}
                    </div>

                    <div className="dgw-modal-actions">
                        <Button
                            isSecondary
                            onClick={closeConfirmModal}
                            disabled={loading}
                        >
                            {__('Cancel', 'dgwltd-revision-manager')}
                        </Button>
                        <Button
                            isPrimary
                            onClick={() => handleSetCurrent(confirmModal.id)}
                            disabled={loading}
                        >
                            {loading ? <Spinner /> : __('Set as Current', 'dgwltd-revision-manager')}
                        </Button>
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default RevisionTimeline;