// Direct login script - include this in any page with a script tag to debug authentication
(function() {
  // Log authentication status
  console.log('Auth Fix script loaded');
  
  function checkAuth() {
    const storedUser = localStorage.getItem('securehealth_user');
    console.log('Current securehealth_user:', storedUser);
    
    if (!storedUser) {
      console.log('No user in localStorage');
      return;
    }
    
    try {
      const user = JSON.parse(storedUser);
      console.log('Parsed user:', user);
      
      // Fix user object if needed
      let fixed = false;
      
      if (!user.email && user.username) {
        user.email = user.username;
        fixed = true;
        console.log('Added missing email from username');
      }
      
      if (!user.username && user.email) {
        user.username = user.email;
        fixed = true;
        console.log('Added missing username from email');
      }
      
      if (!user.roles) {
        user.roles = ['ROLE_USER'];
        fixed = true;
        console.log('Added missing roles');
      } else if (!Array.isArray(user.roles)) {
        user.roles = [user.roles];
        fixed = true;
        console.log('Converted roles to array');
      }
      
      // If any fixes were applied, update localStorage
      if (fixed) {
        console.log('Fixing user object in localStorage');
        localStorage.setItem('securehealth_user', JSON.stringify(user));
        console.log('Fixed user saved:', JSON.stringify(user));
      }
      
    } catch (e) {
      console.error('Error parsing stored user:', e);
    }
  }
  
  // Check and fix auth on load
  checkAuth();
  
  // Expose global function
  window.fixAuth = checkAuth;
  
  console.log('Auth fix complete - call window.fixAuth() to run again');
})();