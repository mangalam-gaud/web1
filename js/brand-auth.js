// ===========================
// BRAND AUTHENTICATION
// ===========================

async function handleBrandLogin(event) {
    event.preventDefault();
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    clearFormMessage(form);
    
    const email = document.getElementById('login-email').value.trim();
    const password = document.getElementById('login-password').value;
    
    if (!validateEmail(email)) {
        showNotification('Please enter a valid email', 'error');
        showFormMessage(form, 'Please enter a valid email.', 'error');
        return;
    }
    
    if (!validatePassword(password)) {
        showNotification('Password must be at least 6 characters', 'error');
        showFormMessage(form, 'Password must be at least 6 characters.', 'error');
        return;
    }
    
    try {
        setButtonLoading(submitBtn, true);
        
        const response = await apiCall('brand/login', 'POST', {
            email: email,
            password: password
        });
        
        if (response.status === 'success') {
            saveUserData({
                ...response.data,
                type: 'brand'
            });
            
            showNotification('Login successful!', 'success');
            setTimeout(() => {
                window.location.href = 'brand-dashboard.html';
            }, 1000);
        } else {
            showNotification(response.message || 'Login failed', 'error');
            showFormMessage(form, response.message || 'Invalid credentials. Please try again.', 'error');
        }
    } catch (error) {
        showNotification(error.message || 'Login failed', 'error');
        showFormMessage(form, error.message || 'Invalid credentials. Please try again.', 'error');
    } finally {
        setButtonLoading(submitBtn, false);
    }
}

async function handleBrandRegister(event) {
    event.preventDefault();
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    clearFormMessage(form);
    
    const brandName = document.getElementById('register-brand-name').value.trim();
    const email = document.getElementById('register-email').value.trim();
    const password = document.getElementById('register-password').value;
    const confirm = document.getElementById('register-confirm').value;
    
    // Validation
    if (!brandName || brandName.length < 2) {
        showNotification('Please enter a valid brand name', 'error');
        showFormMessage(form, 'Please enter a valid brand name.', 'error');
        return;
    }
    
    if (!validateEmail(email)) {
        showNotification('Please enter a valid email', 'error');
        showFormMessage(form, 'Please enter a valid email.', 'error');
        return;
    }
    
    if (!validatePassword(password)) {
        showNotification('Password must be at least 6 characters', 'error');
        showFormMessage(form, 'Password must be at least 6 characters.', 'error');
        return;
    }
    
    if (password !== confirm) {
        showNotification('Passwords do not match', 'error');
        showFormMessage(form, 'Passwords do not match.', 'error');
        return;
    }
    
    try {
        setButtonLoading(submitBtn, true);
        
        const response = await apiCall('brand/register', 'POST', {
            brand_name: brandName,
            email: email,
            password: password
        });
        
        if (response.status === 'success') {
            saveUserData({
                ...response.data,
                type: 'brand'
            });
            
            showNotification('Registration successful! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = 'brand-dashboard.html';
            }, 1000);
        } else {
            showNotification(response.message || 'Registration failed', 'error');
            showFormMessage(form, response.message || 'Registration failed. Please try again.', 'error');
        }
    } catch (error) {
        showNotification(error.message || 'Registration failed', 'error');
        showFormMessage(form, error.message || 'Registration failed. Please try again.', 'error');
    } finally {
        setButtonLoading(submitBtn, false);
    }
}

// Check authentication on page load
document.addEventListener('DOMContentLoaded', function() {
    const user = getUserData();
    if (user && user.type === 'brand') {
        window.location.href = 'brand-dashboard.html';
    }
});
