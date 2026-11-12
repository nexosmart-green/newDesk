import { QueryClient, QueryObserver } from '@tanstack/react-query'
import { allExcept, refreshQueries } from './refreshQueries'

/** A mounted query (only active queries refetch on invalidation). */
function observe(client: QueryClient, queryKey: readonly unknown[], queryFn: () => Promise<string>) {
  const observer = new QueryObserver(client, { queryKey, queryFn })
  return observer.subscribe(() => undefined)
}

const never = () => new Promise<string>(() => undefined)

describe('refreshQueries', () => {
  it('resolves once `now` is fresh, without waiting for the background refreshes', async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    client.setQueryData(['screen'], 'old')
    client.setQueryData(['elsewhere'], 'old')
    const screenFetch = vi.fn().mockResolvedValue('new')
    const slowFetch = vi.fn(never)
    observe(client, ['screen'], screenFetch)
    observe(client, ['elsewhere'], slowFetch)
    screenFetch.mockClear()

    await refreshQueries(client, { now: [['screen']], later: [['elsewhere']] })

    expect(client.getQueryData(['screen'])).toBe('new')
    expect(client.isFetching({ queryKey: ['elsewhere'] })).toBe(1)
  })

  it('a `later` key covering a `now` key reuses its request', async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity } } })
    const fetchList = vi.fn().mockResolvedValue('list')
    observe(client, ['tasks', 'list'], fetchList)
    await vi.waitFor(() => expect(client.getQueryData(['tasks', 'list'])).toBe('list'))
    fetchList.mockClear()

    await refreshQueries(client, { now: [['tasks', 'list']], later: [['tasks']] })

    expect(fetchList).toHaveBeenCalledTimes(1)
  })

  it('allExcept skips the query the mutation already wrote', async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity } } })
    const fetchDetail = vi.fn().mockResolvedValue('detail')
    const fetchList = vi.fn().mockResolvedValue('list')
    observe(client, ['people', 'detail', 1], fetchDetail)
    observe(client, ['people', 'list'], fetchList)
    await vi.waitFor(() => expect(client.getQueryData(['people', 'list'])).toBe('list'))
    fetchDetail.mockClear()
    fetchList.mockClear()

    await refreshQueries(client, { now: [allExcept(['people'], ['people', 'detail', 1])] })

    expect(fetchList).toHaveBeenCalledTimes(1)
    expect(fetchDetail).not.toHaveBeenCalled()
  })
})
