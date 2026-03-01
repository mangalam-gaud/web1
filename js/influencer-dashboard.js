// ===========================
// INFLUENCER DASHBOARD
// ===========================

let currentUser = null;
let currentChatId = null;
let currentChatPartnerId = null;
let allCampaigns = [];

document.addEventListener('DOMContentLoaded', async function() {
    // Check authentication
    currentUser = getUserData();
    if (!currentUser || currentUser.type !== 'influencer') {
        window.location.href = 'influencer-auth.html';
        return;
    }
    
    // Initialize dashboard
    initializeDashboard();
    loadDashboardData();
    loadCampaigns();
    loadAppliedCampaigns();
    loadProfile();
    loadWallet();
    loadChats();
    loadNotifications();
    setInterval(loadNotifications, 30000);
});

function initializeDashboard() {
    const profileImg = document.getElementById('profileImg');
    if (profileImg) {
        profileImg.src = getAvatarUrl(currentUser.name);
    }
    const heroName = document.getElementById('influencerHeroName');
    if (heroName) {
        heroName.textContent = currentUser.name || 'Creator';
    }

    bindLiveSearch('searchCampaigns', '#campaignsList .campaign-card', (item) => item.innerText);
    bindInfluencerProfileLivePreview();
}

function normalizeProgress(value) {
    const parsed = parseInt(value, 10);
    if (Number.isNaN(parsed)) return 0;
    return Math.max(0, Math.min(100, parsed));
}

function getProgressLabel(progress) {
    if (progress >= 100) return 'Completed';
    if (progress >= 70) return 'Near Finish';
    if (progress >= 35) return 'In Progress';
    if (progress > 0) return 'Started';
    return 'Not Started';
}

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function getInfluencerProfileStrengthTier(score) {
    if (score >= 85) return { label: 'Excellent', tone: 'excellent' };
    if (score >= 65) return { label: 'Strong', tone: 'strong' };
    if (score >= 40) return { label: 'Growing', tone: 'growing' };
    return { label: 'Needs Improvement', tone: 'needs-work' };
}

function buildInfluencerProfileState(profile = null) {
    const fromField = (id) => document.getElementById(id)?.value || '';
    const source = profile || {
        name: fromField('editName'),
        contact: fromField('editContact'),
        about: fromField('editAbout'),
        experience: fromField('editExperience'),
        hourly_rate: fromField('editRate'),
        instagram: fromField('editInstagram'),
        youtube: fromField('editYoutube'),
        facebook: fromField('editFacebook'),
        twitter: fromField('editTwitter')
    };

    const state = {
        name: String(source.name || '').trim(),
        contact: String(source.contact || '').trim(),
        about: String(source.about || '').trim(),
        experience: String(source.experience || '').trim(),
        hourly_rate: parseFloat(source.hourly_rate || 0) || 0,
        instagram: String(source.instagram || '').trim(),
        youtube: String(source.youtube || '').trim(),
        facebook: String(source.facebook || '').trim(),
        twitter: String(source.twitter || '').trim()
    };

    const completedFields = [
        state.contact,
        state.about,
        state.experience,
        state.instagram,
        state.youtube,
        state.facebook,
        state.twitter,
        state.hourly_rate > 0
    ].filter(Boolean).length;

    return {
        ...state,
        strength: Math.round((completedFields / 8) * 100)
    };
}

function updateInfluencerProfileWidgets(profile = null) {
    const state = buildInfluencerProfileState(profile);
    const tier = getInfluencerProfileStrengthTier(state.strength);
    const displayName = state.name || 'Your Name';

    const strengthValue = document.getElementById('influencerProfileStrengthValue');
    const strengthTier = document.getElementById('influencerProfileTier');
    const strengthBar = document.getElementById('influencerProfileStrengthBar');
    if (strengthValue) strengthValue.textContent = `${state.strength}%`;
    if (strengthTier) {
        strengthTier.textContent = tier.label;
        strengthTier.dataset.tier = tier.tone;
    }
    if (strengthBar) {
        strengthBar.style.width = `${state.strength}%`;
        strengthBar.dataset.tier = tier.tone;
    }

    const profileName = document.getElementById('profileName');
    const profileImageLarge = document.getElementById('profileImageLarge');
    const profileAvatar = document.getElementById('profileImg');
    const heroName = document.getElementById('influencerHeroName');
    if (profileName) profileName.textContent = displayName;
    if (profileImageLarge) profileImageLarge.src = getAvatarUrl(displayName);
    if (profileAvatar) profileAvatar.src = getAvatarUrl(displayName);
    if (heroName && state.name) heroName.textContent = state.name;

    const previewName = document.getElementById('influencerPreviewName');
    const previewAbout = document.getElementById('influencerPreviewAbout');
    const previewRate = document.getElementById('influencerPreviewRate');
    const previewExperience = document.getElementById('influencerPreviewExperience');
    const previewInstagram = document.getElementById('influencerPreviewInstagram');
    const previewYoutube = document.getElementById('influencerPreviewYoutube');
    const previewFacebook = document.getElementById('influencerPreviewFacebook');
    const previewTwitter = document.getElementById('influencerPreviewTwitter');

    if (previewName) previewName.textContent = displayName;
    if (previewAbout) previewAbout.textContent = state.about || 'Add a clear creator bio to attract premium brands.';
    if (previewRate) previewRate.textContent = state.hourly_rate > 0 ? formatCurrency(state.hourly_rate) : '$0.00';
    if (previewExperience) {
        const exp = state.experience;
        previewExperience.textContent = exp ? (exp.length > 42 ? `${exp.slice(0, 39)}...` : exp) : 'Not set';
    }
    if (previewInstagram) previewInstagram.textContent = `Instagram: ${state.instagram || 'Not linked'}`;
    if (previewYoutube) previewYoutube.textContent = `YouTube: ${state.youtube || 'Not linked'}`;
    if (previewFacebook) previewFacebook.textContent = `Facebook: ${state.facebook || 'Not linked'}`;
    if (previewTwitter) previewTwitter.textContent = `Twitter: ${state.twitter || 'Not linked'}`;
}

function bindInfluencerProfileLivePreview() {
    const form = document.querySelector('#profile-tab .profile-form');
    if (!form || form.dataset.previewBound === 'true') {
        return;
    }

    const inputIds = [
        'editName',
        'editContact',
        'editAbout',
        'editExperience',
        'editRate',
        'editInstagram',
        'editYoutube',
        'editFacebook',
        'editTwitter'
    ];

    inputIds.forEach((id) => {
        const field = document.getElementById(id);
        if (field) {
            field.addEventListener('input', () => updateInfluencerProfileWidgets());
        }
    });

    form.dataset.previewBound = 'true';
}

function renderAppliedCampaignProgress(campaign) {
    const progress = normalizeProgress(campaign.progress);
    const updatedText = campaign.progress_updated_at ? `${formatDate(campaign.progress_updated_at)} ${formatTime(campaign.progress_updated_at)}` : 'No updates yet';
    const notePreview = campaign.progress_note ? escapeHtml(campaign.progress_note) : 'No progress note yet.';

    if (campaign.application_status !== 'accepted') {
        return `
            <div class="application-progress-block read-only">
                <div class="progress-label-row">
                    <span>Campaign Progress</span>
                    <strong>${progress}%</strong>
                </div>
                <div class="progress-track"><span style="width:${progress}%"></span></div>
                <p class="progress-note-preview">${notePreview}</p>
                <p class="progress-meta-text">Last update: ${updatedText}</p>
            </div>
        `;
    }

    return `
        <div class="application-progress-block editable">
            <div class="progress-label-row">
                <span>Campaign Progress</span>
                <strong id="progressValue-${campaign.application_id}">${progress}%</strong>
            </div>
            <div class="progress-track"><span id="progressLiveBar-${campaign.application_id}" style="width:${progress}%"></span></div>
            <p class="progress-note-preview">${getProgressLabel(progress)} | Last update: ${updatedText}</p>
            <label class="progress-input-label" for="progressRange-${campaign.application_id}">Update delivery progress</label>
            <input id="progressRange-${campaign.application_id}" class="progress-range-input" type="range" min="0" max="100" step="5" value="${progress}" oninput="syncProgressValue(${campaign.application_id})">
            <textarea id="progressNote-${campaign.application_id}" class="progress-note-input" rows="2" placeholder="Share what was completed in this update...">${escapeHtml(campaign.progress_note || '')}</textarea>
            <button class="btn btn-primary btn-sm" onclick="saveApplicationProgress(${campaign.application_id}, this)">Save Progress</button>
        </div>
    `;
}

function syncProgressValue(applicationId) {
    const range = document.getElementById(`progressRange-${applicationId}`);
    const valueLabel = document.getElementById(`progressValue-${applicationId}`);
    const liveBar = document.getElementById(`progressLiveBar-${applicationId}`);
    if (!range || !valueLabel) return;
    const progress = normalizeProgress(range.value);
    valueLabel.textContent = `${progress}%`;
    if (liveBar) {
        liveBar.style.width = `${progress}%`;
    }
}

async function saveApplicationProgress(applicationId, triggerButton) {
    const range = document.getElementById(`progressRange-${applicationId}`);
    const noteField = document.getElementById(`progressNote-${applicationId}`);
    if (!range) return;

    const payload = {
        influencer_id: currentUser.id,
        progress: normalizeProgress(range.value),
        progress_note: noteField ? noteField.value.trim() : ''
    };

    setButtonLoading(triggerButton, true, 'Saving...');
    try {
        const response = await apiCall(`campaign/application-progress/${applicationId}`, 'PUT', payload);
        if (response.status === 'success') {
            showNotification('Progress updated successfully!', 'success');
            loadAppliedCampaigns();
            loadDashboardData();
        } else {
            showNotification(response.message || 'Failed to update progress', 'error');
        }
    } catch (error) {
        showNotification(error.message || 'Failed to update progress', 'error');
    } finally {
        setButtonLoading(triggerButton, false);
    }
}

async function loadDashboardData() {
    try {
        const profileResponse = await apiCall(`influencer/profile/${currentUser.id}`);
        const campaignsResponse = await apiCall(`influencer/applied-campaigns/${currentUser.id}`);
        const walletResponse = await apiCall(`influencer/wallet/${currentUser.id}`);
        let profileScore = 0;
        
        if (profileResponse.status === 'success') {
            const profile = profileResponse.data;
            animateCount(document.getElementById('userRating'), parseFloat(profile.rating || 0), { float: true });
            const heroName = document.getElementById('influencerHeroName');
            if (heroName) {
                heroName.textContent = profile.name || currentUser.name || 'Creator';
            }
            profileScore = buildInfluencerProfileState(profile).strength;
            updateInfluencerProfileWidgets(profile);
        }
        
        if (campaignsResponse.status === 'success') {
            const campaigns = campaignsResponse.data;
            const waiting = campaigns.filter(c => c.application_status === 'waiting').length;
            const accepted = campaigns.filter(c => c.application_status === 'accepted').length;
            const total = campaigns.length;
            const acceptanceRate = total > 0 ? Math.round((accepted / total) * 100) : 0;
            const acceptedBudget = campaigns
                .filter(c => c.application_status === 'accepted')
                .reduce((sum, c) => sum + (parseFloat(c.payout) || 0), 0);
            const totalBudget = campaigns.reduce((sum, c) => sum + (parseFloat(c.payout) || 0), 0);
            const budgetRate = totalBudget > 0 ? Math.round((acceptedBudget / totalBudget) * 100) : 0;
             
            animateCount(document.getElementById('activeCampaigns'), waiting);
            animateCount(document.getElementById('acceptedCampaigns'), accepted);
            animateCount(document.getElementById('influencerHeroWaiting'), waiting);
            animateCount(document.getElementById('influencerHeroAccepted'), accepted);
            document.getElementById('appBadge').textContent = waiting;
            const acceptBar = document.getElementById('influencerAcceptBar');
            const acceptText = document.getElementById('influencerAcceptText');
            const profileBar = document.getElementById('influencerProfileBar');
            const profileText = document.getElementById('influencerProfileText');
            if (acceptBar) acceptBar.style.width = `${acceptanceRate}%`;
            if (acceptText) acceptText.textContent = `${acceptanceRate}%`;
            if (profileBar) profileBar.style.width = `${profileScore}%`;
            if (profileText) profileText.textContent = `${profileScore}%`;

            animateCount(document.getElementById('creatorMomentumApplied'), total);
            animateCount(document.getElementById('creatorMomentumAccepted'), accepted);
            animateCount(document.getElementById('creatorMomentumWaiting'), waiting);
            const momentumProfile = document.getElementById('creatorMomentumProfile');
            if (momentumProfile) momentumProfile.textContent = `${profileScore}%`;

            const creatorAcceptanceBar = document.getElementById('creatorAcceptanceBar');
            const creatorAcceptanceText = document.getElementById('creatorAcceptanceText');
            const creatorBudgetBar = document.getElementById('creatorBudgetBar');
            const creatorBudgetText = document.getElementById('creatorBudgetText');
            if (creatorAcceptanceBar) creatorAcceptanceBar.style.width = `${acceptanceRate}%`;
            if (creatorAcceptanceText) creatorAcceptanceText.textContent = `${acceptanceRate}%`;
            if (creatorBudgetBar) creatorBudgetBar.style.width = `${budgetRate}%`;
            if (creatorBudgetText) creatorBudgetText.textContent = formatCurrency(acceptedBudget);
            
            // Show recent campaigns
            const recent = campaigns.slice(0, 3);
            const recentContainer = document.getElementById('recentCampaigns');
            if (recent.length > 0) {
                recentContainer.innerHTML = recent.map(campaign => `
                    <div class="campaign-card" onclick="openCampaignModal('${JSON.stringify(campaign).replace(/'/g, "&apos;")}')">
                        <div class="campaign-card-header">
                            <h3>${escapeHtml(campaign.field)}</h3>
                            <p>${escapeHtml(campaign.brand_name)}</p>
                        </div>
                        <div class="campaign-card-body">
                            <div class="campaign-info">
                                <div class="campaign-info-item">
                                    <span class="campaign-info-label">Status</span>
                                    <span class="campaign-info-value">${escapeHtml(campaign.application_status)}</span>
                                </div>
                                <div class="campaign-info-item">
                                    <span class="campaign-info-label">Budget</span>
                                    <span class="campaign-info-value">${formatCurrency(campaign.payout)}</span>
                                </div>
                            </div>
                            ${campaign.application_status === 'accepted' ? `
                                <div class="application-progress-block compact">
                                    <div class="progress-label-row">
                                        <span>Progress</span>
                                        <strong>${normalizeProgress(campaign.progress)}%</strong>
                                    </div>
                                    <div class="progress-track"><span style="width:${normalizeProgress(campaign.progress)}%"></span></div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `).join('');
            }
        }
        
        if (walletResponse.status === 'success') {
            document.getElementById('totalEarnings').textContent = formatCurrency(walletResponse.data.total_earnings);
            document.getElementById('walletEarnings').textContent = formatCurrency(walletResponse.data.total_earnings);
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
    }
}

async function loadCampaigns() {
    try {
        const response = await apiCall(`campaign?status=active`);
        const campaignsList = document.getElementById('campaignsList');
        
        if (response.status === 'success') {
            const campaigns = response.data;
            allCampaigns = campaigns;
            animateCount(document.getElementById('influencerHeroOpen'), campaigns.length);
            if (campaigns.length === 0) {
                campaignsList.innerHTML = '<p class="empty-state">No campaigns available</p>';
                return;
            }
            
            campaignsList.innerHTML = campaigns.map(campaign => `
                <div class="campaign-card">
                    <div class="campaign-card-header">
                        <h3>${escapeHtml(campaign.field)}</h3>
                        <p>${escapeHtml(campaign.brand_name)}</p>
                    </div>
                    <div class="campaign-card-body">
                        <p>${escapeHtml(campaign.overview ? campaign.overview.substring(0, 100) : '')}...</p>
                        <div class="campaign-info">
                            <div class="campaign-info-item">
                                <span class="campaign-info-label">Duration</span>
                                <span class="campaign-info-value">${escapeHtml(campaign.duration || 'N/A')}</span>
                            </div>
                            <div class="campaign-info-item">
                                <span class="campaign-info-label">Budget</span>
                                <span class="campaign-info-value">${formatCurrency(campaign.payout)}</span>
                            </div>
                        </div>
                        <div class="campaign-actions">
                            <button class="btn btn-primary btn-sm" onclick="applyCampaign(${campaign.id})">Apply Now</button>
                            <button class="btn btn-secondary btn-sm" onclick="viewCampaignDetails(${campaign.id})">Details</button>
                            <button class="btn btn-secondary btn-sm" onclick="viewBrandProfile(${campaign.brand_id})">Brand Profile</button>
                        </div>
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Error loading campaigns:', error);
        document.getElementById('campaignsList').innerHTML = '<p class="empty-state">Error loading campaigns</p>';
    }
}

async function loadAppliedCampaigns() {
    try {
        const response = await apiCall(`influencer/applied-campaigns/${currentUser.id}`);
        const campaignsList = document.getElementById('appliedCampaignsList');
        
        if (response.status === 'success') {
            const campaigns = response.data;
            if (campaigns.length === 0) {
                campaignsList.innerHTML = '<p class="empty-state">You haven\'t applied for any campaigns yet</p>';
                return;
            }
            
            campaignsList.innerHTML = campaigns.map(campaign => `
                <div class="campaign-card">
                    <div class="campaign-card-header">
                        <h3>${escapeHtml(campaign.field)}</h3>
                        <p>${escapeHtml(campaign.brand_name)}</p>
                    </div>
                    <div class="campaign-card-body">
                        <div class="campaign-info">
                            <div class="campaign-info-item">
                                <span class="campaign-info-label">Application Status</span>
                                <span class="campaign-info-value">${escapeHtml(campaign.application_status)}</span>
                            </div>
                            <div class="campaign-info-item">
                                <span class="campaign-info-label">Budget</span>
                                <span class="campaign-info-value">${formatCurrency(campaign.payout)}</span>
                            </div>
                        </div>
                        ${renderAppliedCampaignProgress(campaign)}
                        <div class="campaign-actions">
                            <button class="btn btn-secondary btn-sm" onclick="viewCampaignDetails(${campaign.id})">View Details</button>
                            <button class="btn btn-secondary btn-sm" onclick="viewBrandProfile(${campaign.brand_id})">Brand Profile</button>
                        </div>
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Error loading applied campaigns:', error);
        document.getElementById('appliedCampaignsList').innerHTML = '<p class="empty-state">Error loading campaigns</p>';
    }
}

async function applyCampaign(campaignId) {
    try {
        const response = await apiCall('campaign/apply', 'POST', {
            campaign_id: campaignId,
            influencer_id: currentUser.id
        });
        
        if (response.status === 'success') {
            showNotification('Application submitted successfully!', 'success');
            loadCampaigns();
            loadAppliedCampaigns();
        } else {
            showNotification(response.message || 'Failed to apply', 'error');
        }
    } catch (error) {
        showNotification(error.message || 'Failed to apply', 'error');
    }
}

async function viewCampaignDetails(campaignId) {
    try {
        const response = await apiCall(`campaign/details/${campaignId}`);
        if (response.status === 'success') {
            openCampaignModal(response.data);
        }
    } catch (error) {
        showNotification('Error loading campaign details', 'error');
    }
}

async function loadProfile() {
    try {
        const response = await apiCall(`influencer/profile/${currentUser.id}`);
        
        if (response.status === 'success') {
            const profile = response.data;
            const name = profile.name || currentUser.name || 'Your Name';
            const email = profile.email || currentUser.email || '';
            
            // Set profile display
            document.getElementById('profileName').textContent = name;
            document.getElementById('profileEmail').textContent = email;
            document.getElementById('profileImageLarge').src = getAvatarUrl(name);
            const profileImg = document.getElementById('profileImg');
            if (profileImg) {
                profileImg.src = getAvatarUrl(name);
            }
            const heroName = document.getElementById('influencerHeroName');
            if (heroName) {
                heroName.textContent = name;
            }
            currentUser.name = name;
            animateCount(document.getElementById('userRating'), parseFloat(profile.rating || 0), { float: true });
            
            // Set form fields
            document.getElementById('editName').value = name;
            document.getElementById('editContact').value = profile.contact || '';
            document.getElementById('editAbout').value = profile.about || '';
            document.getElementById('editExperience').value = profile.experience || '';
            document.getElementById('editRate').value = profile.hourly_rate || '';
            document.getElementById('editInstagram').value = profile.instagram || '';
            document.getElementById('editYoutube').value = profile.youtube || '';
            document.getElementById('editFacebook').value = profile.facebook || '';
            document.getElementById('editTwitter').value = profile.twitter || '';

            bindInfluencerProfileLivePreview();
            updateInfluencerProfileWidgets(profile);
        }
    } catch (error) {
        console.error('Error loading profile:', error);
    }
}

async function handleProfileUpdate(event) {
    event.preventDefault();
    
    try {
        const updateData = {
            name: document.getElementById('editName').value.trim(),
            contact: document.getElementById('editContact').value.trim(),
            about: document.getElementById('editAbout').value.trim(),
            experience: document.getElementById('editExperience').value.trim(),
            hourly_rate: parseFloat(document.getElementById('editRate').value) || 0,
            instagram: document.getElementById('editInstagram').value.trim(),
            youtube: document.getElementById('editYoutube').value.trim(),
            facebook: document.getElementById('editFacebook').value.trim(),
            twitter: document.getElementById('editTwitter').value.trim()
        };
        
        const response = await apiCall(`influencer/profile/${currentUser.id}`, 'PUT', updateData);
        
        if (response.status === 'success') {
            showNotification('Profile updated successfully!', 'success');
            loadProfile();
        } else {
            showNotification(response.message || 'Failed to update profile', 'error');
        }
    } catch (error) {
        showNotification(error.message || 'Failed to update profile', 'error');
    }
}

async function loadWallet() {
    try {
        const response = await apiCall(`influencer/wallet/${currentUser.id}`);
        
        if (response.status === 'success') {
            const wallet = response.data;
            document.getElementById('walletEarnings').textContent = formatCurrency(wallet.total_earnings);
            document.getElementById('accountNumber').value = wallet.account_number || '';
            document.getElementById('ifscCode').value = wallet.ifsc_code || '';
        }
    } catch (error) {
        console.error('Error loading wallet:', error);
    }
}

async function handleWalletUpdate(event) {
    event.preventDefault();
    
    try {
        const updateData = {
            account_number: document.getElementById('accountNumber').value,
            ifsc_code: document.getElementById('ifscCode').value
        };
        
        const response = await apiCall(`influencer/wallet/${currentUser.id}`, 'PUT', updateData);
        
        if (response.status === 'success') {
            showNotification('Bank details updated successfully!', 'success');
        } else {
            showNotification(response.message || 'Failed to update details', 'error');
        }
    } catch (error) {
        showNotification(error.message || 'Failed to update details', 'error');
    }
}

async function loadNotifications() {
    try {
        const response = await apiCall(`notification/list/${currentUser.id}?type=influencer`);
        const container = document.getElementById('notificationsList');
        const badge = document.getElementById('notifBadge');
        if (response.status !== 'success') return;

        const list = response.data || [];
        const unread = response.meta?.unread_count || 0;
        if (badge) {
            badge.textContent = unread;
            badge.style.display = unread > 0 ? 'inline-block' : 'none';
        }

        if (!container) return;
        if (list.length === 0) {
            container.innerHTML = '<p class="empty-state">No notifications yet</p>';
            return;
        }

        container.innerHTML = list.map(item => `
            <div class="notification-item ${item.is_read ? '' : 'notification-unread'}">
                <div class="notification-item-head">
                    <h4>${escapeHtml(item.title)}</h4>
                    <small>${formatDate(item.created_at)} ${formatTime(item.created_at)}</small>
                </div>
                <p>${escapeHtml(item.message)}</p>
                ${item.is_read ? '' : `<button class="btn btn-secondary btn-sm" onclick="markNotificationRead(${item.id}, this)">Mark Read</button>`}
            </div>
        `).join('');
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

async function markNotificationRead(notificationId, button) {
    try {
        if (button) setButtonLoading(button, true, 'Updating...');
        await apiCall(`notification/read/${notificationId}`, 'PUT', {});
        loadNotifications();
    } catch (error) {
        showNotification(error.message || 'Unable to mark notification', 'error');
    } finally {
        if (button) setButtonLoading(button, false);
    }
}

async function markAllNotificationsRead() {
    try {
        await apiCall('notification/read-all', 'PUT', {});
        loadNotifications();
    } catch (error) {
        showNotification(error.message || 'Unable to mark notifications', 'error');
    }
}

async function loadChats() {
    try {
        const response = await apiCall(`chat/list/${currentUser.id}?type=influencer`);
        const chatsList = document.getElementById('chatsList');
        
        if (response.status === 'success' && response.data.length > 0) {
            chatsList.innerHTML = response.data.map(chat => `
                <div class="chat-item">
                    <div class="chat-item-body">
                        <div class="chat-item-title">${chat.brand_name}</div>
                        <div class="chat-item-preview">${chat.unread_count > 0 ? `${chat.unread_count} new messages` : 'No new messages'}</div>
                    </div>
                    <div class="chat-item-actions">
                        <button class="btn btn-primary btn-sm" onclick="openChat(${chat.brand_id}, decodeURIComponent('${encodeURIComponent(chat.brand_name || '')}'))">Message</button>
                        <button class="btn btn-secondary btn-sm" onclick="viewBrandProfile(${chat.brand_id})">View Profile</button>
                    </div>
                </div>
            `).join('');
        } else {
            chatsList.innerHTML = '<p class="empty-state">No conversations yet</p>';
        }
    } catch (error) {
        console.error('Error loading chats:', error);
    }
}

async function openChat(brandId, brandName) {
    try {
        const response = await apiCall('chat/get', 'POST', {
            influencer_id: currentUser.id,
            brand_id: brandId
        });
        
        if (response.status === 'success') {
            currentChatId = response.data.chat_id;
            currentChatPartnerId = brandId;
            
            document.getElementById('noChat').classList.add('hidden');
            document.getElementById('chatBox').classList.remove('hidden');
            document.getElementById('chatTitle').textContent = brandName;
            const profileBtn = document.getElementById('chatProfileBtn');
            if (profileBtn) {
                profileBtn.classList.remove('hidden');
                profileBtn.disabled = false;
            }
            
            loadMessages();
        }
    } catch (error) {
        showNotification('Error opening chat', 'error');
    }
}

async function loadMessages() {
    try {
        const response = await apiCall(`chat/messages/${currentChatId}`);
        const messagesContainer = document.getElementById('messagesContainer');
        
        if (response.status === 'success') {
            messagesContainer.innerHTML = response.data.map(msg => `
                <div class="message ${msg.sender_id === currentUser.id ? 'sent' : 'received'}">
                    <div class="message-content">${escapeHtml(msg.message)}</div>
                </div>
            `).join('');
            
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    } catch (error) {
        console.error('Error loading messages:', error);
    }
}

async function handleSendMessage(event) {
    event.preventDefault();
    
    const messageInput = document.getElementById('messageInput');
    const message = messageInput.value.trim();
    
    if (!message) return;
    
    try {
        const response = await apiCall('chat/message', 'POST', {
            chat_id: currentChatId,
            sender_id: currentUser.id,
            sender_type: 'influencer',
            message: message
        });
        
        if (response.status === 'success') {
            messageInput.value = '';
            loadMessages();
        }
    } catch (error) {
        showNotification('Failed to send message', 'error');
    }
}

function openActiveChatProfile() {
    if (!currentChatPartnerId) {
        showNotification('Select a conversation first', 'warning');
        return;
    }
    viewBrandProfile(currentChatPartnerId);
}

async function viewBrandProfile(brandId) {
    if (!brandId) {
        showNotification('Brand profile is unavailable for this campaign', 'warning');
        return;
    }

    try {
        const response = await apiCall(`brand/profile/${brandId}`);
        if (response.status !== 'success') {
            showNotification('Error loading brand profile', 'error');
            return;
        }

        const brand = response.data;
        const modal = document.getElementById('brandModal');
        const details = document.getElementById('brandDetails');

        details.innerHTML = `
            <div class="influencer-modal-content">
                <img src="${getAvatarUrl(brand.brand_name || 'Brand')}" alt="${brand.brand_name || 'Brand'}" style="width: 100px; height: 100px; border-radius: 50%; display: block; margin: 0 auto 1rem;">
                <h2>${brand.brand_name || 'Brand'}</h2>
                <p style="text-align: center; color: var(--gray); margin-bottom: 1.5rem;">${brand.email || ''}</p>

                <div class="modal-section">
                    <h3>About</h3>
                    <p>${brand.about || 'No description available'}</p>
                </div>

                <div class="modal-info">
                    <div class="info-item">
                        <label>Owner Name</label>
                        <p>${brand.owner_name || 'N/A'}</p>
                    </div>
                    <div class="info-item">
                        <label>Contact</label>
                        <p>${brand.contact || 'N/A'}</p>
                    </div>
                </div>

                <div class="modal-info">
                    <div class="info-item">
                        <label>Instagram</label>
                        <p>${brand.instagram || 'N/A'}</p>
                    </div>
                    <div class="info-item">
                        <label>LinkedIn</label>
                        <p>${brand.owner_linkedin || 'N/A'}</p>
                    </div>
                </div>

                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="closeBrandModal()">Close</button>
                </div>
            </div>
        `;

        modal.classList.remove('hidden');
    } catch (error) {
        showNotification('Error loading brand profile', 'error');
    }
}

function closeBrandModal() {
    const modal = document.getElementById('brandModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Function to handle modal campaign opening
function openCampaignModal(campaign) {
    const modal = document.getElementById('campaignModal');
    const details = document.getElementById('campaignDetails');
    
    // Parse if it's a string
    if (typeof campaign === 'string') {
        try {
            campaign = JSON.parse(campaign);
        } catch (e) {
            return;
        }
    }
    
    let alreadyApplied = false;
    if (campaign.application_id) {
        alreadyApplied = true;
    }
    
    details.innerHTML = `
        <div class="campaign-modal-content">
            <h2>${campaign.field}</h2>
            <p class="brand-name">${campaign.brand_name || 'Unknown Brand'}</p>
            
            <div class="modal-section">
                <h3>Overview</h3>
                <p>${campaign.overview}</p>
            </div>
            
            ${campaign.work_details ? `
                <div class="modal-section">
                    <h3>Work Details</h3>
                    <p>${campaign.work_details}</p>
                </div>
            ` : ''}
            
            <div class="modal-info">
                <div class="info-item">
                    <label>Duration</label>
                    <p>${campaign.duration || 'Not Specified'}</p>
                </div>
                <div class="info-item">
                    <label>Budget</label>
                    <p>${formatCurrency(campaign.payout)}</p>
                </div>
                <div class="info-item">
                    <label>Status</label>
                    <p><span class="badge badge-${campaign.status}">${campaign.status}</span></p>
                </div>
            </div>
            ${campaign.application_status === 'accepted' ? `
                <div class="modal-section">
                    <h3>Current Progress</h3>
                    <div class="application-progress-block compact">
                        <div class="progress-label-row">
                            <span>Delivery</span>
                            <strong>${normalizeProgress(campaign.progress)}%</strong>
                        </div>
                        <div class="progress-track"><span style="width:${normalizeProgress(campaign.progress)}%"></span></div>
                        <p class="progress-note-preview">${campaign.progress_note ? escapeHtml(campaign.progress_note) : 'No progress note yet.'}</p>
                    </div>
                </div>
            ` : ''}
             
            <div class="modal-actions">
                ${alreadyApplied ? `
                    <button class="btn btn-secondary" disabled>Already Applied</button>
                ` : `
                    <button class="btn btn-primary" onclick="applyCampaign(${campaign.id})">Apply Now</button>
                `}
                ${campaign.brand_id ? `
                    <button class="btn btn-secondary" onclick="closeCampaignModal(); viewBrandProfile(${campaign.brand_id})">View Brand Profile</button>
                ` : ''}
                <button class="btn btn-secondary" onclick="closeCampaignModal()">Close</button>
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
}
