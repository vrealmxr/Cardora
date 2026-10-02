import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'

function PageViewTracker() {
  const { pathname, search } = useLocation()

  useEffect(() => {
    window.dataLayer = window.dataLayer || []
    window.dataLayer.push({
      event: 'page_view',
      page_path: pathname + search,
      page_location: window.location.href,
      page_title: document.title,
    })
  }, [pathname, search])

  return null
}

export default PageViewTracker
