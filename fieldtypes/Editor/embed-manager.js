/**
 * Embed Manager for Advanced Editor
 * Handles platform-specific embed generation and management
 */

console.log('🚀 Loading EmbedManager...');
console.log('🚀 Script execution started');

try {
  window.EmbedManager = {
  
  // Platform detection patterns
  platforms: {
    youtube: {
      patterns: ['youtube.com', 'youtu.be'],
      extractId: (url) => {
        const regex = /(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([^&\n?#]+)/;
        const match = url.match(regex);
        return match ? match[1] : null;
      },
      generateEmbed: (url, videoId) => {
        return `<div class="embed-container youtube-embed" style="
          position: relative; 
          padding-bottom: 56.25%; 
          height: 0; 
          overflow: hidden; 
          max-width: 100%; 
          border-radius: 8px; 
          margin: 10px 0;
          border: 1px solid #e1e5e9;
          background: #000;
        ">
          <iframe style="
            position: absolute; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            border-radius: 8px;
          " 
            src="https://www.youtube.com/embed/${videoId}?rel=0&modestbranding=1&showinfo=0" 
            frameborder="0" 
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
            allowfullscreen>
          </iframe>
        </div>`;
      }
    },
    
    vimeo: {
      patterns: ['vimeo.com'],
      extractId: (url) => {
        const regex = /vimeo\.com\/(\d+)/;
        const match = url.match(regex);
        return match ? match[1] : null;
      },
      generateEmbed: (url, videoId) => {
        return `<div class="embed-container vimeo-embed" style="
          position: relative; 
          padding-bottom: 56.25%; 
          height: 0; 
          overflow: hidden; 
          max-width: 100%; 
          border-radius: 8px;
          margin: 10px 0;
          border: 1px solid #e1e5e9;
          background: #000;
        ">
          <iframe style="
            position: absolute; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            border-radius: 8px;
          " 
            src="https://player.vimeo.com/video/${videoId}?title=0&byline=0&portrait=0&color=ffffff" 
            frameborder="0" 
            allow="autoplay; fullscreen; picture-in-picture" 
            allowfullscreen>
          </iframe>
        </div>`;
      }
    },
    
    twitter: {
      patterns: ['twitter.com', 'x.com'],
      extractId: (url) => {
        // Extract tweet ID from Twitter URL
        const regex = /(?:twitter\.com|x\.com)\/[^\/]+\/status\/(\d+)/;
        const match = url.match(regex);
        return match ? match[1] : null;
      },
      generateEmbed: (url, tweetId) => {
        return `<div class="embed-container twitter-embed" style="
          max-width: 550px; 
          margin: 10px auto;
          border: 1px solid #e1e5e9;
          border-radius: 8px;
          overflow: hidden;
        ">
          <blockquote class="twitter-tweet" data-theme="light" data-dnt="true">
            <a href="${url}"></a>
          </blockquote>
          <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
        </div>`;
      }
    },
    
    instagram: {
      patterns: ['instagram.com'],
      extractId: (url) => {
        // Extract post ID from Instagram URL
        const regex = /instagram\.com\/p\/([^\/\?#]+)/;
        const match = url.match(regex);
        return match ? match[1] : null;
      },
      generateEmbed: (url, postId) => {
        return `<div class="embed-container instagram-embed" style="
          max-width: 550px; 
          margin: 10px auto;
          border: 1px solid #e1e5e9;
          border-radius: 8px;
          overflow: hidden;
        ">
          <blockquote class="instagram-media" 
            data-instgrm-permalink="${url}" 
            data-instgrm-version="14"
            data-instgrm-captioned>
          </blockquote>
          <script async src="//www.instagram.com/embed.js"></script>
        </div>`;
      }
    },
    
    facebook: {
      patterns: ['facebook.com'],
      extractId: (url) => {
        // Extract post ID from Facebook URL
        const regex = /facebook\.com\/[^\/]+\/posts\/(\d+)/;
        const match = url.match(regex);
        return match ? match[1] : null;
      },
      generateEmbed: (url, postId) => {
        return `<div class="embed-container facebook-embed" style="
          max-width: 550px; 
          margin: 10px auto;
          border: 1px solid #e1e5e9;
          border-radius: 8px;
          overflow: hidden;
        ">
          <div id="fb-root"></div>
          <script async defer crossorigin="anonymous" 
            src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v18.0">
          </script>
          <div class="fb-post" 
            data-href="${url}" 
            data-width="550" 
            data-show-text="true"
            data-lazy="true">
          </div>
        </div>`;
      }
    },
    
    linkedin: {
      patterns: ['linkedin.com'],
      extractId: (url) => {
        // Extract post ID from LinkedIn URL
        const regex = /linkedin\.com\/posts\/[^\/]+\/activity-(\d+)/;
        const match = url.match(regex);
        return match ? match[1] : null;
      },
      generateEmbed: (url, postId) => {
        return `<div class="embed-container linkedin-embed" style="
          max-width: 550px; 
          margin: 10px auto;
          border: 1px solid #e1e5e9;
          border-radius: 8px;
          overflow: hidden;
        ">
          <iframe src="https://www.linkedin.com/embed/feed/update/urn:li:activity:${postId}" 
            height="400" width="550" frameborder="0" allowfullscreen>
          </iframe>
        </div>`;
      }
    },
    
    tiktok: {
      patterns: ['tiktok.com'],
      extractId: (url) => {
        // Extract video ID from TikTok URL
        const regex = /tiktok\.com\/@[^\/]+\/video\/(\d+)/;
        const match = url.match(regex);
        return match ? match[1] : null;
      },
      generateEmbed: (url, videoId) => {
        return `<div class="embed-container tiktok-embed" style="
          max-width: 325px; 
          margin: 10px auto;
          border: 1px solid #e1e5e9;
          border-radius: 8px;
          overflow: hidden;
        ">
          <blockquote class="tiktok-embed" 
            cite="${url}" 
            data-video-id="${videoId}"
            style="max-width: 325px; min-width: 325px;">
          </blockquote>
          <script async src="https://www.tiktok.com/embed.js"></script>
        </div>`;
      }
    },
    
    spotify: {
      patterns: ['spotify.com'],
      extractId: (url) => {
        // Extract track/album/playlist ID from Spotify URL
        const regex = /spotify\.com\/(track|album|playlist)\/([a-zA-Z0-9]+)/;
        const match = url.match(regex);
        return match ? { type: match[1], id: match[2] } : null;
      },
      generateEmbed: (url, data) => {
        const embedUrl = url.replace('open.spotify.com', 'open.spotify.com/embed');
        return `<div class="embed-container spotify-embed" style="
          max-width: 300px; 
          margin: 10px auto;
          border: 1px solid #e1e5e9;
          border-radius: 8px;
          overflow: hidden;
        ">
          <iframe style="border-radius:12px" 
            src="${embedUrl}" 
            width="100%" 
            height="${data.type === 'track' ? '352' : '380'}" 
            frameborder="0" 
            allowfullscreen>
          </iframe>
        </div>`;
      }
    },
    
    github: {
      patterns: ['github.com'],
      extractId: (url) => {
        // Extract username and repository from GitHub URL
        const regex = /github\.com\/([^\/]+)\/([^\/\?#]+)/;
        const match = url.match(regex);
        return match ? { user: match[1], repo: match[2] } : null;
      },
      generateEmbed: (url, data) => {
        return `<div class="embed-container github-embed" style="
          text-align: center; 
          padding: 20px; 
          border: 1px solid #e1e5e9; 
          border-radius: 8px; 
          background: #f8f9fa;
          margin: 10px 0;
        ">
          <h3 style="margin: 0 0 10px 0; color: #333;">GitHub Repository</h3>
          <p style="margin: 0 0 15px 0; color: #666;">${data.user}/${data.repo}</p>
          <iframe src="https://ghbtns.com/github-btn.html?user=${data.user}&repo=${data.repo}&type=star&count=true&size=large" 
            frameborder="0" scrolling="0" width="170" height="30">
          </iframe>
          <div style="margin-top: 15px;">
            <a href="${url}" target="_blank" style="
              display: inline-block;
              padding: 8px 16px;
              background: #24292e;
              color: white;
              text-decoration: none;
              border-radius: 6px;
              font-weight: 500;
            ">View on GitHub</a>
          </div>
        </div>`;
      }
    }
  },

  // Detect platform from URL
  detectPlatform(url) {
    for (const [platform, config] of Object.entries(this.platforms)) {
      if (config.patterns.some(pattern => url.includes(pattern))) {
        return platform;
      }
    }
    return null;
  },

  // Generate embed HTML for a given URL
  generateEmbed(url) {
    console.log('🔗 Generating embed for URL:', url);
    
    const platform = this.detectPlatform(url);
    if (!platform) {
      console.log('🌐 No specific platform detected, using general website embed');
      return this.createGeneralWebsiteEmbed(url);
    }
    
    console.log('🎯 Platform detected:', platform);
    const config = this.platforms[platform];
    const id = config.extractId(url);
    
    if (!id) {
      console.log('❌ Could not extract ID for platform:', platform);
      return this.createGeneralWebsiteEmbed(url);
    }
    
    console.log('✅ ID extracted:', id);
    return config.generateEmbed(url, id);
  },

  // Create general website embed for unknown URLs
  createGeneralWebsiteEmbed(url) {
    return `<div class="embed-container website-embed" style="
      border: 1px solid #e1e5e9;
      border-radius: 8px;
      padding: 20px;
      background: white;
      max-width: 100%;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      margin: 10px 0;
      overflow: hidden;
    ">
      <div style="display: flex; align-items: flex-start; gap: 15px; flex-wrap: wrap;">
        <div style="
          width: 60px;
          height: 60px;
          background: #f8f9fa;
          border-radius: 8px;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 24px;
          color: #6c757d;
          flex-shrink: 0;
        ">🌐</div>
        <div style="flex: 1; min-width: 0;">
          <h3 style="
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 600;
            color: #333;
            line-height: 1.3;
          ">Website Preview</h3>
          <p style="
            margin: 0 0 12px 0;
            color: #666;
            line-height: 1.5;
            font-size: 14px;
          ">This content will be embedded from the external website.</p>
          <div style="
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #6c757d;
            word-break: break-all;
          ">
            <span>🔗</span>
            <a href="${url}" target="_blank" style="
              color: #1a73e8;
              text-decoration: none;
              word-break: break-all;
              max-width: 100%;
              display: block;
            ">${url.length > 50 ? url.substring(0, 47) + '...' : url}</a>
          </div>
        </div>
      </div>
      <div style="
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #f1f3f4;
        text-align: center;
      ">
        <button onclick="window.open('${url}', '_blank')" style="
          padding: 8px 16px;
          background: #1a73e8;
          color: white;
          border: none;
          border-radius: 6px;
          cursor: pointer;
          font-weight: 500;
          transition: background-color 0.2s;
        " onmouseover="this.style.background='#1557b0'" onmouseout="this.style.background='#1a73e8'">
          Open Website
        </button>
      </div>
    </div>`;
  }
};

console.log('✅ EmbedManager loaded successfully!');
console.log('🔍 Available platforms:', Object.keys(window.EmbedManager.platforms));

} catch (error) {
  console.error('❌ Error loading EmbedManager:', error);
  console.error('❌ Error stack:', error.stack);
}
